<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ServerStatsService
{
    public function __construct(
        private readonly XuiClientFactory $xui,
    ) {}

    public function refresh(Server $server): void
    {
        if ($server->is_coming_soon) {
            return;
        }

        try {
            $ping = $this->measureTcpPing($server->host, (int) $server->port);
            $capacity = max(1, (int) $server->capacity_clients);
            $clientCount = $this->xui->forServer($server)->clientCount();
            $load = (int) min(100, floor($clientCount * 100 / $capacity));

            $server->forceFill([
                'ping_ms' => $ping,
                'load_percent' => $load,
                'stats_updated_at' => Carbon::now(),
                'last_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('ServerStats: refresh failed', [
                'slug' => $server->slug,
                'error' => $e->getMessage(),
            ]);
            $server->forceFill([
                'stats_updated_at' => Carbon::now(),
                'last_error' => substr($e->getMessage(), 0, 500),
            ])->save();
        }
    }

    private function measureTcpPing(string $host, int $port, float $timeout = 2.0): ?int
    {
        $start = microtime(true);
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return null;
        }
        fclose($socket);
        return (int) round((microtime(true) - $start) * 1000);
    }
}
