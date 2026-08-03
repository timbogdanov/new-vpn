<?php

namespace App\Console\Commands;

use App\DTO\VpnClientDTO;
use App\Models\Server;
use App\Models\TelegramUser;
use App\Models\VpnClient;
use App\Services\TelegramMessageService;
use App\Services\XuiClientFactory;
use App\Services\XuiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;

/**
 * Sends a localized proactive Telegram DM to every user who allows PMs. Built
 * for the server-migration reconnect: with --with-config it appends each user's
 * own self-contained vless:// link, which connects straight to the (new, clean)
 * node IP and therefore works even when the app/subscription domain is blocked
 * — the durable channel when everything web-facing is down but Telegram is not.
 */
class TelegramBroadcast extends Command
{
    protected $signature = 'telegram:broadcast
        {--key= : Lang key for the localized body (e.g. broadcast.migration)}
        {--text-en= : Ad-hoc EN body (overrides --key for EN)}
        {--text-ru= : Ad-hoc RU body (overrides --key for RU)}
        {--with-config= : Server slug; append each user\'s raw vless config for that server}
        {--config-required : Skip users with no active client on the --with-config server}
        {--open-app : Add an "Open App" web_app button to the Mini App}
        {--tag= : Dedupe/resume marker; each recipient is sent at most once per tag}
        {--rate=25 : Messages per second (Telegram allows ~30/s to different chats)}
        {--limit=0 : Max recipients (0 = no cap)}
        {--dry-run : Print recipient count + sample renders; send nothing}';

    protected $description = "Broadcast a localized Telegram DM to all users who allow PMs, optionally injecting each user's VPN config for a migration reconnect.";

    public function handle(TelegramMessageService $messenger, XuiClientFactory $xuiFactory): int
    {
        $key = $this->option('key');
        $textEn = $this->option('text-en');
        $textRu = $this->option('text-ru');
        if (!$key && !$textEn && !$textRu) {
            $this->error('Provide --key=<lang.key> or --text-en= / --text-ru=.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $openApp = (bool) $this->option('open-app');
        $configRequired = (bool) $this->option('config-required');
        $tag = (string) ($this->option('tag') ?? '');
        $rate = max(1, (int) $this->option('rate'));
        $sleepMicros = (int) (1_000_000 / $rate);
        $limit = max(0, (int) $this->option('limit'));

        $configServer = null;
        $xui = null;
        if ($slug = $this->option('with-config')) {
            $configServer = Server::where('slug', $slug)->first();
            if (!$configServer) {
                $this->error("No server with slug={$slug} for --with-config");
                return self::FAILURE;
            }
            $xui = $xuiFactory->forServer($configServer);
        }

        $query = TelegramUser::query()->where('allows_write_to_pm', true);
        $matched = (clone $query)->count();

        $this->info(sprintf(
            'Broadcast: matched=%d config=%s open_app=%s tag=%s rate=%d/s%s',
            $matched,
            $configServer?->slug ?? 'no',
            $openApp ? 'yes' : 'no',
            $tag !== '' ? $tag : '<none>',
            $rate,
            $dryRun ? ' (DRY RUN)' : '',
        ));

        if ($dryRun) {
            foreach (['ru', 'en'] as $loc) {
                $this->newLine();
                $this->line("--- sample [{$loc}] ---");
                $this->line($this->bodyFor($loc, $key, $textRu, $textEn));
                if ($configServer) {
                    $this->line($this->configBlockFor($loc));
                    $this->line('<code>vless://…(per-user, self-contained)…</code>');
                }
                if ($openApp) {
                    $this->line('[button] ' . Lang::get('broadcast.open_app_button', [], $loc));
                }
            }
            $this->newLine();
            $this->info('Dry run: nothing sent.');
            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        $skippedDedupe = 0;
        $skippedNoConfig = 0;
        $processed = 0;

        $query->orderBy('telegram_id')->chunkById(100, function ($users) use (
            $messenger, $xui, $configServer, $configRequired, $openApp, $tag, $sleepMicros, $limit,
            $key, $textRu, $textEn,
            &$sent, &$failed, &$skippedDedupe, &$skippedNoConfig, &$processed,
        ) {
            foreach ($users as $user) {
                if ($limit > 0 && $processed >= $limit) {
                    return false; // stop chunking — cap reached
                }

                $locale = in_array($user->language_code, ['en', 'ru'], true) ? $user->language_code : 'en';
                $body = $this->bodyFor($locale, $key, $textRu, $textEn);

                if ($configServer) {
                    $vless = $this->rawVlessFor($user, $configServer, $xui);
                    if ($vless === null) {
                        if ($configRequired) {
                            $skippedNoConfig++;
                            continue;
                        }
                    } else {
                        $body .= "\n\n" . $this->configBlockFor($locale)
                            . "\n<code>" . htmlspecialchars($vless, ENT_QUOTES | ENT_SUBSTITUTE) . '</code>';
                    }
                }

                if ($tag !== '') {
                    $fresh = Cache::add("bcast:{$tag}:{$user->telegram_id}", 1, now()->addDays(7));
                    if (!$fresh) {
                        $skippedDedupe++;
                        continue;
                    }
                }

                $keyboard = $openApp ? $this->openAppKeyboard($locale) : [];
                $ok = $messenger->send((int) $user->telegram_id, $body, $keyboard);
                $processed++;

                if ($ok) {
                    $sent++;
                } else {
                    $failed++;
                    // Release the dedupe marker so a later retry pass re-sends.
                    if ($tag !== '') {
                        Cache::forget("bcast:{$tag}:{$user->telegram_id}");
                    }
                }

                usleep($sleepMicros);
            }
        }, 'telegram_id');

        $this->info(sprintf(
            'Done: sent=%d failed=%d skipped(dedupe)=%d skipped(no-config)=%d',
            $sent,
            $failed,
            $skippedDedupe,
            $skippedNoConfig,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function bodyFor(string $locale, ?string $key, ?string $textRu, ?string $textEn): string
    {
        $adhoc = $locale === 'ru' ? $textRu : $textEn;
        if ($adhoc !== null && $adhoc !== '') {
            return $adhoc;
        }
        if ($key) {
            return (string) Lang::get($key, [], $locale);
        }
        // No key and no ad-hoc for this locale → use whatever ad-hoc exists.
        return (string) (($locale === 'ru' ? $textEn : $textRu) ?? '');
    }

    private function configBlockFor(string $locale): string
    {
        return (string) Lang::get('broadcast.manual_config', [], $locale);
    }

    private function openAppKeyboard(string $locale): array
    {
        return [[[
            'text' => (string) Lang::get('broadcast.open_app_button', [], $locale),
            'web_app' => ['url' => (string) config('miniapp.app_url')],
        ]]];
    }

    /**
     * The user's self-contained vless:// link for a server, or null if they
     * have no enabled client there. Self-contained = connects directly to the
     * node IP with no web fetch, so it survives app/subscription-domain blocks.
     */
    private function rawVlessFor(TelegramUser $user, Server $server, XuiService $xui): ?string
    {
        $client = VpnClient::query()
            ->where('telegram_user_id', $user->telegram_id)
            ->where('server_id', $server->id)
            ->where('enabled', true)
            ->first();

        if (!$client) {
            return null;
        }

        try {
            return $xui->getVlessLink(
                new VpnClientDTO(
                    uuid: $client->uuid,
                    email: $client->email,
                    telegramId: (int) $client->telegram_user_id,
                    subId: $client->sub_id,
                    enabled: (bool) $client->enabled,
                    expiryTime: 0,
                    totalGB: 0,
                ),
                serverAddress: $server->host,
                remark: $server->flag_emoji . ' ' . $server->name,
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
