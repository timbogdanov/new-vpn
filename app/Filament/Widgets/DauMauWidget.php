<?php

namespace App\Filament\Widgets;

use App\Models\TelegramUser;
use Filament\Widgets\StatsOverviewWidget as Base;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class DauMauWidget extends Base
{
    protected function getStats(): array
    {
        $now = Carbon::now();
        $dau = TelegramUser::where('last_active_at', '>=', $now->copy()->subDay())->count();
        $wau = TelegramUser::where('last_active_at', '>=', $now->copy()->subWeek())->count();
        $mau = TelegramUser::where('last_active_at', '>=', $now->copy()->subMonth())->count();

        return [
            Stat::make('DAU', $dau)->description('last 24h'),
            Stat::make('WAU', $wau)->description('last 7d'),
            Stat::make('MAU', $mau)->description('last 30d'),
        ];
    }
}
