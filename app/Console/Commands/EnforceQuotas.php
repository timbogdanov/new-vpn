<?php

namespace App\Console\Commands;

use App\Models\VpnClient;
use App\Services\XuiClientFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class EnforceQuotas extends Command
{
    protected $signature = 'billing:enforce-quotas';

    protected $description = 'Disable VpnClient rows whose quota is exhausted or subscription has expired.';

    public function handle(XuiClientFactory $factory): int
    {
        $reasonQuota = (string) config('billing.enforcement.reason_quota', 'quota_exhausted');
        $reasonExpired = (string) config('billing.enforcement.reason_expired', 'subscription_expired');
        $hardCutoff = (bool) config('billing.enforcement.hard_cutoff', true);

        $now = Carbon::now();
        $count = ['expired' => 0, 'quota' => 0, 'errors' => 0];

        $candidates = VpnClient::query()
            ->where('enabled', true)
            ->where(function ($q) use ($now) {
                $q->whereNotNull('expires_at')->where('expires_at', '<=', $now)
                    ->orWhereNotNull('quota_bytes');
            })
            ->with('server')
            ->get();

        foreach ($candidates as $client) {
            $reason = null;

            if ($client->expires_at !== null && $client->expires_at->isPast()) {
                $reason = $reasonExpired;
            } elseif ($client->quota_bytes !== null) {
                $used = (int) $client->last_traffic_up + (int) $client->last_traffic_down;
                if ($used >= (int) $client->quota_bytes) {
                    $reason = $reasonQuota;
                }
            }

            if ($reason === null) {
                continue;
            }

            $client->forceFill([
                'enabled' => false,
                'disabled_reason' => $reason,
            ])->save();

            $reason === $reasonQuota ? $count['quota']++ : $count['expired']++;

            if (!$hardCutoff || !$client->server) {
                continue;
            }

            try {
                $xui = $factory->forServer($client->server);
                $xui->disableClient($client->uuid);
            } catch (\Throwable $e) {
                $count['errors']++;
                Log::warning('billing:enforce-quotas: panel disable failed', [
                    'vpn_client_id' => $client->id,
                    'server' => $client->server->slug,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf(
            'enforced: expired=%d quota=%d errors=%d',
            $count['expired'], $count['quota'], $count['errors'],
        ));

        return self::SUCCESS;
    }
}
