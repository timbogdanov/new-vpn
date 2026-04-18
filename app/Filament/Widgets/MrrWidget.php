<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as Base;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class MrrWidget extends Base
{
    protected function getStats(): array
    {
        $stars30d = (int) Payment::where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', Carbon::now()->subDays(30))
            ->sum('stars_amount');

        $stars7d = (int) Payment::where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', Carbon::now()->subDays(7))
            ->sum('stars_amount');

        $usd30 = Money::starsToUsdEstimate($stars30d);
        $usd7 = Money::starsToUsdEstimate($stars7d);

        return [
            Stat::make('Last 30d revenue', '★ ' . number_format($stars30d))->description("≈ \${$usd30}"),
            Stat::make('Last 7d revenue', '★ ' . number_format($stars7d))->description("≈ \${$usd7}"),
        ];
    }
}
