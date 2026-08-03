<?php

namespace App\Console\Commands;

use App\DTO\VpnClientDTO;
use App\Models\Server;
use App\Models\TelegramUser;
use App\Models\VpnClient;
use App\Services\AggregatedSubscriptionService;
use App\Services\XuiClientFactory;
use Illuminate\Console\Command;

/**
 * Re-adds a server's existing VPN clients to its 3x-ui panel, preserving each
 * client's uuid/sub_id. Run this after pointing a server slug at a NEW or
 * replacement panel (e.g. an RKN-block migration): the fresh panel's inbound
 * starts empty, so without this every already-imported user config embeds a
 * uuid the new panel doesn't recognise and fails to authenticate.
 *
 * Idempotent: uuids already present on the panel are skipped, so it is safe to
 * re-run (after a partial failure, a roll-forward to another IP, etc).
 */
class Reprovision extends Command
{
    protected $signature = 'vpn:reprovision
        {slug=primary : Server slug whose clients should be re-added to its panel}
        {--dry-run : Report what would happen; write nothing to the panel}
        {--include-disabled : Also re-add clients whose DB row is disabled}
        {--batch=50 : Clients per addClient call (and DB page size)}
        {--user=* : Restrict to specific telegram_ids (repeatable); default all}';

    protected $description = "Re-add a server's existing VPN clients (preserving uuid/sub_id) to its 3x-ui panel after a server/panel migration.";

    public function handle(XuiClientFactory $xuiFactory, AggregatedSubscriptionService $agg): int
    {
        $slug = (string) $this->argument('slug');
        $server = Server::where('slug', $slug)->first();
        if (!$server) {
            $this->error("No server with slug={$slug}");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $batchSize = max(1, (int) $this->option('batch'));
        $onlyUsers = array_values(array_filter(array_map('intval', (array) $this->option('user'))));

        $xui = $xuiFactory->forServer($server);

        // Snapshot what already lives on the (new) panel so we skip re-adding —
        // this is what makes the command idempotent and re-runnable.
        try {
            $xui->forgetInboundCache();
            $existing = array_flip(
                collect($xui->getAllClients())->pluck('uuid')->filter()->all()
            );
        } catch (\Throwable $e) {
            $this->error("Cannot read the panel inbound for [{$slug}]: " . $e->getMessage());
            return self::FAILURE;
        }

        $query = VpnClient::query()->where('server_id', $server->id);
        if (!$this->option('include-disabled')) {
            $query->where('enabled', true);
        }
        if ($onlyUsers) {
            $query->whereIn('telegram_user_id', $onlyUsers);
        }

        $candidates = (clone $query)->count();
        $this->info(sprintf(
            'Reprovision [%s] server_id=%d — candidates=%d, already-on-panel=%d%s',
            $slug,
            $server->id,
            $candidates,
            count($existing),
            $dryRun ? ' (DRY RUN)' : '',
        ));

        $reprovisioned = 0;
        $skipped = 0;
        $failed = 0;
        $affected = [];

        $query->orderBy('id')->chunkById($batchSize, function ($rows) use (
            $xui, $dryRun, &$reprovisioned, &$skipped, &$failed, &$affected, $existing,
        ) {
            /** @var array<int, VpnClientDTO> $dtos */
            $dtos = [];
            foreach ($rows as $row) {
                if (isset($existing[$row->uuid])) {
                    $skipped++;
                    continue;
                }
                $dtos[] = new VpnClientDTO(
                    uuid: $row->uuid,
                    email: $row->email,
                    telegramId: (int) $row->telegram_user_id,
                    subId: $row->sub_id,
                    enabled: (bool) $row->enabled,
                    expiryTime: 0,
                    totalGB: 0,
                );
            }

            if ($dtos === []) {
                return;
            }

            if ($dryRun) {
                foreach ($dtos as $dto) {
                    $reprovisioned++;
                    if ($dto->telegramId !== null) {
                        $affected[$dto->telegramId] = true;
                    }
                }
                return;
            }

            $results = $xui->addExistingClients($dtos);

            foreach ($dtos as $dto) {
                $ok = $results[$dto->uuid] ?? false;
                // A multi-client batch that failed maps all uuids to false;
                // retry each individually (a benign duplicate resolves to true).
                if (!$ok && count($dtos) > 1) {
                    $ok = $xui->addExistingClient($dto);
                }
                if ($ok) {
                    $reprovisioned++;
                    if ($dto->telegramId !== null) {
                        $affected[$dto->telegramId] = true;
                    }
                } else {
                    $failed++;
                    $this->warn("  failed: uuid={$dto->uuid} email={$dto->email}");
                }
            }
        }, 'id');

        // Drop each affected user's aggregated-subscription cache so the next
        // fetch rebuilds against the freshly-populated panel.
        if (!$dryRun && $affected !== []) {
            TelegramUser::whereIn('telegram_id', array_keys($affected))
                ->get()
                ->each(fn (TelegramUser $u) => $agg->invalidate($u));
        }

        $this->info(sprintf(
            'Done: reprovisioned=%d skipped(existing)=%d failed=%d affected_users=%d dry_run=%s',
            $reprovisioned,
            $skipped,
            $failed,
            count($affected),
            $dryRun ? 'yes' : 'no',
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
