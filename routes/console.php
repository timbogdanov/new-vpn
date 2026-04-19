<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('servers:refresh-stats')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('billing:enforce-quotas')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('ooni:warm-cache --force')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('ooni:diff-watchlists')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();
