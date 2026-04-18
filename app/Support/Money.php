<?php

namespace App\Support;

class Money
{
    public static function starsToUsdEstimate(int $stars): float
    {
        $rate = max(1, (int) config('billing.stars_per_usd_estimate', 50));
        return round($stars / $rate, 2);
    }

    public static function formatStars(int $stars): string
    {
        return '★ ' . number_format($stars);
    }
}
