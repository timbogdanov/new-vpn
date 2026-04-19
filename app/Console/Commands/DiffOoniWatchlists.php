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

    protected $description = "Compare each watchlisted user's current OONI verdicts vs their last snapshot; push a Telegram alert when any status flips. Supports both curated service keys and arbitrary watched URLs.";

    public function handle(OoniService $ooni, TelegramMessageService $messenger): int
    {
        $sent = 0; $scanned = 0; $skipped = 0; $errors = 0;

        $labels = [];
        foreach ((array) config('ooni.services', []) as $svc) {
            $labels[$svc['key']] = $svc['label'];
        }

        $recommended = Server::query()->where('is_coming_soon', false)->orderBy('load_percent')->first();
        $recommendedName = $recommended?->name ?? 'the recommended server';

        TelegramUser::query()
            ->where(function ($q) {
                $q->whereNotNull('ooni_watchlist')->orWhereNotNull('ooni_watchlist_urls');
            })
            ->where('allows_write_to_pm', true)
            ->chunkById(100, function ($users) use (
                $ooni, $messenger, $labels, $recommendedName,
                &$sent, &$scanned, &$skipped, &$errors,
            ) {
                foreach ($users as $user) {
                    $scanned++;

                    $services = (array) ($user->ooni_watchlist ?? []);
                    $urls = (array) ($user->ooni_watchlist_urls ?? []);
                    if (!$services && !$urls) {
                        $skipped++;
                        continue;
                    }
                    $country = $user->ooni_last_country;
                    $asn = $user->ooni_last_asn;
                    if (!$country) {
                        $skipped++;
                        continue;
                    }

                    $locale = $user->language_code ?: config('app.locale', 'en');
                    $locale = in_array($locale, ['en', 'ru'], true) ? $locale : 'en';

                    // Back-compat: existing snapshots may store bare keys (e.g. 'youtube'
                    // instead of 'service:youtube'). Migrate on read.
                    $prev = $this->upgradeSnapshot((array) ($user->ooni_watchlist_snapshot ?? []));
                    $nextSnapshot = $prev;
                    $flips = [];

                    // Service watchlist
                    if ($services) {
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
                        foreach ($services as $key) {
                            $now = $currentByKey[$key] ?? 'unknown';
                            $snapshotKey = 'service:' . $key;
                            $was = $prev[$snapshotKey]['status'] ?? null;
                            if ($was && $was !== $now) {
                                $flips[] = [
                                    'label' => $labels[$key] ?? $key,
                                    'from' => $was,
                                    'to' => $now,
                                ];
                            }
                            $nextSnapshot[$snapshotKey] = [
                                'status' => $now,
                                'observedAt' => Carbon::now()->toIso8601String(),
                            ];
                        }
                    }

                    // URL watchlist
                    foreach ($urls as $url) {
                        $snapshotKey = 'url:' . sha1($url);
                        try {
                            $details = $ooni->urlDetails($url, $country, $asn, 7);
                        } catch (\Throwable $e) {
                            $errors++;
                            Log::warning('ooni:diff-watchlists: url-details failed', [
                                'telegram_id' => $user->telegram_id,
                                'url' => $url,
                                'error' => $e->getMessage(),
                            ]);
                            continue;
                        }
                        $now = $details->verdictStatus;
                        $was = $prev[$snapshotKey]['status'] ?? null;
                        $host = $details->host;
                        if ($was && $was !== $now) {
                            $flips[] = ['label' => $host, 'from' => $was, 'to' => $now];
                        }
                        $nextSnapshot[$snapshotKey] = [
                            'status' => $now,
                            'observedAt' => Carbon::now()->toIso8601String(),
                            'host' => $host,
                        ];
                    }

                    foreach ($flips as $f) {
                        $unblocked = $f['to'] === 'reachable';
                        $msgKey = $unblocked ? 'ooni.alert_unblocked' : 'ooni.alert_blocked';
                        $text = Lang::get($msgKey, [
                            'service' => $f['label'],
                            'server' => $recommendedName,
                        ], $locale);
                        $ok = $messenger->send((int) $user->telegram_id, $text);
                        if ($ok) {
                            $sent++;
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

    /**
     * Map pre-2026-04-19 bare-key snapshots (`youtube => {status,...}`) onto
     * the namespaced form (`service:youtube => {...}`) so the new diff logic
     * reads them correctly on first run.
     */
    private function upgradeSnapshot(array $prev): array
    {
        $out = [];
        foreach ($prev as $key => $row) {
            if (is_string($key) && (str_starts_with($key, 'service:') || str_starts_with($key, 'url:'))) {
                $out[$key] = $row;
            } else {
                $out['service:' . $key] = $row;
            }
        }
        return $out;
    }
}
