<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Typeahead backing for the universal URL search. Three tiers:
 *   1. Local `ooni_url_suggestions` table (seeded + observed via user searches)
 *   2. `config('ooni.services')` catalog (guaranteed matches for known services)
 *   3. Free-form echo — if the query parses as a domain, surface it even when
 *      we have no prior knowledge so the user can probe brand-new sites.
 *
 * Results are cached per (q, country) for config('ooni.search_cache_ttl').
 */
class OoniSearchService
{
    public function __construct(private readonly OoniUrlNormalizer $normalizer) {}

    public function suggest(string $query, ?string $country = null, int $limit = 10): array
    {
        $q = strtolower(trim($query));
        if ($q === '') {
            return [];
        }
        $country = $country ? strtoupper($country) : null;
        $ttl = (int) config('ooni.search_cache_ttl', 3600);
        $cacheKey = 'ooni:search:' . sha1($q) . ':' . ($country ?: 'XX');

        return Cache::remember($cacheKey, $ttl, fn () => $this->buildSuggestions($q, $country, $limit));
    }

    private function buildSuggestions(string $q, ?string $country, int $limit): array
    {
        $results = [];
        $seen = [];

        $push = function (string $url, string $label, string $host, string $source) use (&$results, &$seen) {
            $key = strtolower($url);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $results[] = [
                'label'   => $label,
                'url'     => $url,
                'urlHash' => sha1($url),
                'host'    => $host,
                'source'  => $source,
            ];
        };

        // Tier 1: local suggestions table
        if (Schema::hasTable('ooni_url_suggestions')) {
            $rows = DB::table('ooni_url_suggestions')
                ->where(function ($b) use ($q) {
                    $b->where('host', 'like', $q . '%')->orWhere('url', 'like', '%' . $q . '%');
                })
                ->when($country, fn ($b) => $b->where(function ($bb) use ($country) {
                    $bb->whereNull('country_code')->orWhere('country_code', $country);
                }))
                ->orderByDesc('popularity')
                ->limit($limit * 2)
                ->get();
            foreach ($rows as $r) {
                $push($r->url, $this->hostLabel($r->host), $r->host, $r->source ?? 'seed');
                if (count($results) >= $limit) break;
            }
        }

        // Tier 2: config catalog (each URL)
        if (count($results) < $limit) {
            $services = (array) config('ooni.services', []);
            foreach ($services as $svc) {
                foreach ((array) ($svc['urls'] ?? []) as $url) {
                    $host = parse_url($url, PHP_URL_HOST) ?: '';
                    if ($host && (str_contains($host, $q) || str_contains(strtolower($svc['label']), $q))) {
                        $push($url, $svc['label'], $host, 'catalog');
                    }
                    if (count($results) >= $limit) break 2;
                }
            }
        }

        // Tier 3: free-form echo if the query looks like a domain
        if (count($results) < $limit && preg_match('/^[a-z0-9][a-z0-9\-.]{1,62}(\.[a-z]{2,})+$/', $q)) {
            $url = $this->normalizer->normalize($q);
            if ($url) {
                $host = parse_url($url, PHP_URL_HOST) ?: $q;
                $push($url, $host, $host, 'free');
            }
        }

        return array_slice($results, 0, $limit);
    }

    /**
     * Called when the user opens the details page for a URL. Bumps popularity
     * so frequent-searches float to the top of the typeahead.
     */
    public function recordSearchHit(string $normalizedUrl, ?string $country = null): void
    {
        if (!Schema::hasTable('ooni_url_suggestions')) {
            return;
        }
        $host = parse_url($normalizedUrl, PHP_URL_HOST) ?: null;
        if (!$host) {
            return;
        }
        $now = Carbon::now();
        $existing = DB::table('ooni_url_suggestions')->where('url', $normalizedUrl)->first();
        if ($existing) {
            DB::table('ooni_url_suggestions')
                ->where('id', $existing->id)
                ->update([
                    'popularity' => (int) $existing->popularity + 1,
                    'last_seen_at' => $now,
                    'updated_at' => $now,
                ]);
            return;
        }
        DB::table('ooni_url_suggestions')->insert([
            'host' => $host,
            'url' => $normalizedUrl,
            'country_code' => $country ? strtoupper($country) : null,
            'popularity' => 1,
            'source' => 'observed',
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function hostLabel(string $host): string
    {
        $h = preg_replace('/^www\./', '', $host);
        $parts = explode('.', (string) $h);
        $core = $parts[0] ?? $h;
        return ucfirst($core);
    }
}
