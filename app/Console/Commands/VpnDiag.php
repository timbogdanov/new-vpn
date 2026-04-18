<?php

namespace App\Console\Commands;

use App\Models\TelegramUser;
use App\Services\AggregatedSubscriptionService;
use App\Services\XuiClientFactory;
use Illuminate\Console\Command;

class VpnDiag extends Command
{
    protected $signature = 'vpn:diag {--tg= : Telegram user ID to inspect}';
    protected $description = "Inspect a user's VPN clients and what each subscription endpoint will actually return";

    public function handle(XuiClientFactory $xuiFactory, AggregatedSubscriptionService $agg): int
    {
        $tgId = $this->option('tg');
        if (!$tgId) {
            $this->error('--tg=<telegram_id> is required');
            return 1;
        }

        $user = TelegramUser::where('telegram_id', $tgId)->first();
        if (!$user) {
            $this->error("No TelegramUser with telegram_id={$tgId}");
            return 1;
        }

        $this->info("User: {$user->telegram_id} ({$user->first_name} {$user->last_name})");
        $this->line('sub_token: ' . ($user->sub_token ?: '<none>'));
        $this->line('universal URL: ' . url('/sub/u/' . $user->getOrGenerateSubToken()));
        $this->newLine();

        $clients = $user->vpnClients()->with('server')->get();
        $this->info('vpn_clients rows in DB: ' . $clients->count());

        if ($clients->isEmpty()) {
            $this->warn('No clients in DB → aggregator will return empty body. Open a server in the Mini App to provision.');
        }

        foreach ($clients as $c) {
            $s = $c->server;
            $this->line(sprintf(
                '  - server=%s active=%s coming_soon=%s sub_id=%s uuid=%s enabled=%s',
                $s?->slug ?? '<missing>',
                $s?->is_active ? 'y' : 'n',
                $s?->is_coming_soon ? 'y' : 'n',
                $c->sub_id !== '' ? $c->sub_id : '<empty>',
                $c->uuid,
                $c->enabled ? 'y' : 'n',
            ));
            if ($s) {
                $this->line('    per-server URL: ' . $s->subscriptionBaseUrl() . '/' . $c->sub_id);
            }
        }

        $this->newLine();
        $this->info("3x-ui inbound view (per server):");
        foreach ($clients->pluck('server')->filter()->unique('id') as $s) {
            try {
                $xui = $xuiFactory->forServer($s);
                $xui->forgetInboundCache();
                $all = $xui->getAllClients();
                $mine = collect($all)->firstWhere('telegramId', (int) $user->telegram_id);
                if (!$mine) {
                    $this->warn("  [{$s->slug}] no matching tgId in 3x-ui inbound");
                    continue;
                }
                $this->line(sprintf(
                    '  [%s] uuid=%s sub_id=%s enable=%s email=%s',
                    $s->slug,
                    $mine->uuid,
                    $mine->subId !== '' ? $mine->subId : '<empty>',
                    $mine->enabled ? 'y' : 'n',
                    $mine->email,
                ));
            } catch (\Throwable $e) {
                $this->error("  [{$s->slug}] xui error: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info('Aggregated subscription body (cache bust + rebuild):');
        $agg->invalidate($user);
        $body = $agg->buildFor($user);
        if ($body === '') {
            $this->warn('  <empty body> — V2RayTun will show group with 0 configs');
        } else {
            $decoded = base64_decode($body, true);
            $this->line('  base64 length: ' . strlen($body));
            $this->line('  decoded lines:');
            foreach (explode("\n", trim((string) $decoded)) as $line) {
                $this->line('    ' . $line);
            }
        }

        return 0;
    }
}
