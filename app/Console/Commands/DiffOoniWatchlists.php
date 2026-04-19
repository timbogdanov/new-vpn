<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Models\TelegramUser;
use App\Services\OoniService;
use App\Services\TelegramMessageService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

class DiffOoniWatchlists extends Command
{
    protected $signature = 'ooni:diff-watchlists';

    protected $description = 'Compare each watchlisted user\'s current OONI verdicts vs their last snapshot; push a Telegram alert when any status flips.';

    public function handle(OoniService $ooni, TelegramMessageService $messenger): int
    {
        $sent = 0; $scanned = 0; $skipped = 0; $errors = 0;

        // Build a labelled map once — saves a config hit per user.
        $labels = [];
        foreach ((array) config('ooni.services', []) as $svc) {
            $labels[$svc['key']] = $svc['label'];
        }

        $recommended = Server::query()->where('is_coming_soon', false)->orderBy('load_percent')->first();
        $recommendedName = $recommended?->name ?? 'the recommended server';

        TelegramUser::query()
            ->whereNotNull('ooni_watchlist')
            ->where('allows_write_to_pm', true)
            ->chunkById(100, function ($users) use (
                $ooni, $messenger, $labels, $recommendedName,
                &$sent, &$scanned, &$skipped, &$errors,
            ) {
                foreach ($users as $user) {
                    $scanned++;

                    $watch = (array) ($user->ooni_watchlist ?? []);
                    if (!$watch) {
                        $skipped++;
                        continue;
                    }
                    $country = $user->ooni_last_country;
                    $asn = $user->ooni_last_asn;
                    if (!$country) {
                        $skipped++;
                        continue;
                    }

                    try {
                        $summary = $ooni->summary($country, $asn);
                    } catch (\Throwable $e) {
                        $errors++;
                        Log::warning('ooni:diff-watchlists: summary failed', [
                            'telegram_id' => $user->telegram_id,
                            'error' => $e->getMessage(),
                        ]);
                        continue;
                    }

                    $currentByKey = [];
                    foreach ($summary->services as $v) {
                        $currentByKey[$v->key] = $v->status;
                    }

                    $prev = (array) ($user->ooni_watchlist_snapshot ?? []);
                    $nextSnapshot = $prev;
                    $flips = [];

                    foreach ($watch as $key) {
                        $now = $currentByKey[$key] ?? 'unknown';
                        $was = $prev[$key]['status'] ?? null;

                        if ($was && $was !== $now) {
                            $flips[] = ['key' => $key, 'from' => $was, 'to' => $now];
                        }
                        $nextSnapshot[$key] = [
                            'status' => $now,
                            'observedAt' => Carbon::now()->toIso8601String(),
                        ];
                    }

                    if ($flips) {
                        foreach ($flips as $f) {
                            $label = $labels[$f['key']] ?? $f['key'];
                            $locale = $user->language_code ?: config('app.locale', 'en');
                            $locale = in_array($locale, ['en', 'ru'], true) ? $locale : 'en';
                            $unblocked = in_array($f['to'], ['reachable'], true);
                            $msgKey = $unblocked ? 'ooni.alert_unblocked' : 'ooni.alert_blocked';
                            $text = Lang::get($msgKey, [
                                'service' => $label,
                                'server' => $recommendedName,
                            ], $locale);
                            $ok = $messenger->send((int) $user->telegram_id, $text);
                            if ($ok) {
                                $sent++;
                            }
                        }
                    }

                    $user->ooni_watchlist_snapshot = $nextSnapshot;
                    $user->save();
                }
            }, 'telegram_id');

        $this->info(sprintf(
            'watchlist-diff: scanned=%d sent=%d skipped=%d errors=%d',
            $scanned, $sent, $skipped, $errors,
        ));
        return self::SUCCESS;
    }
}
