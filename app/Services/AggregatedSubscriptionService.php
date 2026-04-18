<?php

namespace App\Services;

use App\Models\TelegramUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AggregatedSubscriptionService
{
    public function __construct(
        private readonly XuiClientFactory $xui,
    ) {}

    public function buildFor(TelegramUser $user): string
    {
        $ttl = (int) config('miniapp.aggregated_sub_cache_seconds', 60);
        $key = 'agg_sub_' . $user->telegram_id;

        return Cache::remember($key, $ttl, function () use ($user) {
            $lines = [];

            $clients = $user->vpnClients()
                ->with('server')
                ->get()
                ->filter(fn ($c) => $c->server && $c->server->is_active && !$c->server->is_coming_soon);

            foreach ($clients as $client) {
                try {
                    $server = $client->server;
                    $vlessClient = new \App\DTO\VpnClientDTO(
                        uuid: $client->uuid,
                        email: $client->email,
                        telegramId: (int) $client->telegram_user_id,
                        subId: $client->sub_id,
                        enabled: (bool) $client->enabled,
                        expiryTime: 0,
                        totalGB: 0,
                    );
                    $link = $this->xui->forServer($server)->getVlessLink(
                        $vlessClient,
                        serverAddress: $server->host,
                        remark: $server->flag_emoji . ' ' . $server->name,
                    );
                    if ($link) {
                        $lines[] = $link;
                    }
                } catch (\Throwable $e) {
                    Log::warning('AggregatedSub: server skipped', [
                        'server' => $client->server?->slug,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (empty($lines)) {
                return '';
            }

            return base64_encode(implode("\n", $lines) . "\n");
        });
    }

    public function invalidate(TelegramUser $user): void
    {
        Cache::forget('agg_sub_' . $user->telegram_id);
    }
}
