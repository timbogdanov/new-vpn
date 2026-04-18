<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as Base;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ActiveSubsByTierWidget extends Base
{
    protected function getStats(): array
    {
        $rows = Subscription::query()
            ->whereIn('status', [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE])
            ->select('tier', DB::raw('count(*) as c'))
            ->groupBy('tier')
            ->pluck('c', 'tier');

        return [
            Stat::make('Trial', (int) ($rows[Subscription::TIER_TRIAL] ?? 0)),
            Stat::make('Pro', (int) ($rows[Subscription::TIER_PRO] ?? 0)),
            Stat::make('Pro Annual', (int) ($rows[Subscription::TIER_PRO_ANNUAL] ?? 0)),
        ];
    }
}
