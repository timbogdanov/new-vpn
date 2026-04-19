<?php

namespace App\Console\Commands;

use App\Models\CommunityProbeSignal;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily sweep that hard-deletes community_probe_signals rows that were
 * soft-deleted (via My Data → Delete all) more than
 * config('ooni.signal_tombstone_days') days ago. The retention window gives
 * users a chance to realize an accidental delete; after that, the data is
 * gone permanently.
 */
class PurgeOoniTombstones extends Command
{
    protected $signature = 'ooni:purge-tombstones';

    protected $description = 'Hard-delete tombstoned community_probe_signals rows past the retention window.';

    public function handle(): int
    {
        $days = (int) config('ooni.signal_tombstone_days', 30);
        $cutoff = Carbon::now()->subDays($days);

        $deleted = CommunityProbeSignal::query()
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', $cutoff)
            ->delete();

        Log::info('ooni:purge-tombstones', [
            'cutoff' => $cutoff->toIso8601String(),
            'deleted' => $deleted,
        ]);
        $this->info(sprintf('purged tombstones: %d', $deleted));

        return self::SUCCESS;
    }
}
