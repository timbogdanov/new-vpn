<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\ServerStatsService;
use Illuminate\Console\Command;

class RefreshServerStats extends Command
{
    protected $signature = 'servers:refresh-stats';
    protected $description = 'Refresh ping and load stats for every active VPN server';

    public function handle(ServerStatsService $stats): int
    {
        $servers = Server::active()->get();
        $count = 0;

        foreach ($servers as $server) {
            $stats->refresh($server);
            $this->line("  {$server->slug} → load={$server->load_percent}% ping={$server->ping_ms}ms");
            $count++;
        }

        $this->info("Refreshed {$count} servers");
        return 0;
    }
}
