<?php

namespace App\Console\Commands;

use App\Services\OoniService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WarmOoniCache extends Command
{
    protected $signature = 'ooni:warm-cache {--force : Ignore existing cache and re-fetch}';

    protected $description = 'Pre-fetch OONI service verdicts for seeded (country, asn) pairs so first-user requests hit warm cache.';

    public function handle(OoniService $ooni): int
    {
        $seed = (array) config('ooni.seed_warm', []);
        $force = (bool) $this->option('force');
        $ok = 0; $skipped = 0; $errors = 0;

        foreach ($seed as $pair) {
            $country = strtoupper((string) ($pair['country'] ?? ''));
            $asn = $pair['asn'] ?? null;
            $asn = $asn ? strtoupper((string) $asn) : null;

            if (!preg_match('/^[A-Z]{2}$/', $country)) {
                continue;
            }

            $cacheKey = 'ooni:summary:' . $country . ':' . ($asn ?: 'NOASN');
            if (!$force && Cache::has($cacheKey)) {
                $skipped++;
                continue;
            }
            if ($force) {
                Cache::forget($cacheKey);
            }

            try {
                $ooni->summary($country, $asn);
                $ok++;
            } catch (\Throwable $e) {
                $errors++;
                $this->line("error for {$country}/{$asn}: {$e->getMessage()}");
            }
        }

        $this->info(sprintf('warmed: ok=%d skipped=%d errors=%d', $ok, $skipped, $errors));
        return self::SUCCESS;
    }
}
