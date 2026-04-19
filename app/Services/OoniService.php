<?php

namespace App\Services;

use App\DTO\OoniServiceVerdictDTO;
use App\DTO\OoniSummaryDTO;
use App\Models\CommunityProbeSignal;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Queries OONI's aggregation API for per-service censorship verdicts,
 * keyed on the user's detected country + ASN. Results cached per
 * (country, asn) for config('ooni.cache_ttl') seconds.
 *
 * Verdict classifier (per-URL, rolled up worst-of across URLs per service):
 *   confirmed_count > 0              => blocked        (OONI confirmed censorship)
 *   anomaly / measurements >= 0.5    => blocked        (majority anomalous)
 *   anomaly / measurements >= 0.2    => degraded       (partial interference)
 *   measurements >= min_measurements => reachable
 *   otherwise                        => unknown        (thin data)
 *
 * If ASN-scoped query returns thin data across the board, falls back to a
 * country-only query so users see something instead of a wall of 'unknown'.
 * If the community_probe_signals table holds opted-in user data, verdicts
 * are enriched with a communityMeasurementCount and soft-upgraded from
 * 'unknown' to 'degraded' when the community strongly disagrees.
 */
class OoniService
{
    public function summary(string $countryCode, ?string $asn): OoniSummaryDTO
    {
        $country = strtoupper(trim($countryCode)) ?: 'XX';
        $asnKey = $asn ? strtoupper(trim($asn)) : 'NOASN';
        $cacheKey = "ooni:summary:{$country}:{$asnKey}";

        $ttl = (int) config('ooni.cache_ttl', 3600);

        $cached = Cache::get($cacheKey);
        if ($cached instanceof OoniSummaryDTO) {
            return $cached;
        }

        $summary = $this->buildSummary($country, $asn);
        Cache::put($cacheKey, $summary, $ttl);
        return $summary;
    }

    /**
     * Bust the cache for (country, asn) and rebuild. Called when the user
     * explicitly hits Re-check in the UI.
     */
    public function refresh(string $countryCode, ?string $asn): OoniSummaryDTO
    {
        $country = strtoupper(trim($countryCode)) ?: 'XX';
        $asnKey = $asn ? strtoupper(trim($asn)) : 'NOASN';
        Cache::forget("ooni:summary:{$country}:{$asnKey}");
        return $this->summary($country, $asn);
    }

    private function buildSummary(string $country, ?string $asn): OoniSummaryDTO
    {
        $services = (array) config('ooni.services', []);
        $lookback = (int) config('ooni.lookback_days', 7);
        $min = (int) config('ooni.min_measurements', 3);
        $since = Carbon::now('UTC')->subDays($lookback)->toDateString();
        $until = Carbon::now('UTC')->addDay()->toDateString();

        // Flatten every (service_key, url) into a single parallel-pool plan,
        // then fold back per service.
        $plan = [];
        foreach ($services as $svc) {
            foreach ($svc['urls'] as $url) {
                $plan[] = ['key' => $svc['key'], 'label' => $svc['label'], 'url' => $url];
            }
        }

        // Pass 1: ASN-scoped (preferred — precision beats coverage).
        $raw = $this->fetchAggregations($plan, $country, $asn, $since, $until);
        $perService = $this->foldCounts($services, $plan, $raw);

        // If the ASN-scoped query came back basically empty across all
        // services, retry country-only. Users get coarser-but-populated data
        // rather than a wall of 'unknown'.
        $totalMeasurements = array_sum(array_map(fn ($b) => $b['m'], $perService));
        $threshold = $min * max(count($services), 1);
        if ($asn && $totalMeasurements < $threshold) {
            Log::info('OONI: ASN-scoped query thin, falling back to country-only', [
                'country' => $country,
                'asn' => $asn,
                'total_measurements' => $totalMeasurements,
                'threshold' => $threshold,
            ]);
            $raw = $this->fetchAggregations($plan, $country, null, $since, $until);
            $perService = $this->foldCounts($services, $plan, $raw);
        }

        // Pass 2: enrich with our own community signals (opt-in users only).
        $community = $this->communityCountsByService($country, $asn, $lookback);

        $verdicts = [];
        foreach ($services as $svc) {
            $b = $perService[$svc['key']];
            $status = $this->classify($b['m'], $b['conf'], $b['anom']);
            $commBlocked = $community[$svc['key']]['blocked'] ?? 0;
            $commReach = $community[$svc['key']]['reachable'] ?? 0;
            $commTotal = $commBlocked + $commReach;

            // Soft upgrade: if OONI says "unknown" but community strongly agrees
            // that something is off, surface that as 'degraded' (not 'blocked'
            // outright — community signals are noisier than OONI's test list).
            if ($status === 'unknown' && $commTotal >= $min && ($commBlocked / max($commTotal, 1)) >= 0.5) {
                $status = 'degraded';
            }

            $verdicts[] = new OoniServiceVerdictDTO(
                key: $svc['key'],
                label: $svc['label'],
                status: $status,
                measurementCount: $b['m'],
                confirmedCount: $b['conf'],
                anomalyCount: $b['anom'],
                okCount: $b['ok'],
                lastChangeAt: null,
                communityMeasurementCount: $commTotal,
            );
        }

        return new OoniSummaryDTO(
            countryCode: $country,
            asn: $asn,
            asnName: null,
            lookbackDays: $lookback,
            services: $verdicts,
            freshAt: Carbon::now(),
        );
    }

    /**
     * Fold per-URL counts from $raw into per-service totals.
     *
     * @param  array<int, array>  $services
     * @param  array<int, array{key:string,label:string,url:string}>  $plan
     * @param  array<int, ?array>  $raw
     * @return array<string, array{label:string,m:int,ok:int,conf:int,anom:int}>
     */
    private function foldCounts(array $services, array $plan, array $raw): array
    {
        $perService = [];
        foreach ($services as $svc) {
            $perService[$svc['key']] = [
                'label' => $svc['label'],
                'm' => 0, 'ok' => 0, 'conf' => 0, 'anom' => 0,
            ];
        }
        foreach ($plan as $i => $row) {
            $counts = $raw[$i] ?? null;
            if (!$counts) {
                continue;
            }
            $bucket = &$perService[$row['key']];
            $bucket['m']    += (int) ($counts['measurement_count'] ?? 0);
            $bucket['ok']   += (int) ($counts['ok_count']          ?? 0);
            $bucket['conf'] += (int) ($counts['confirmed_count']   ?? 0);
            $bucket['anom'] += (int) ($counts['anomaly_count']     ?? 0);
            unset($bucket);
        }
        return $perService;
    }

    /**
     * @param  array<int, array{key:string,label:string,url:string}>  $plan
     * @return array<int, ?array>  counts keyed by plan index
     */
    private function fetchAggregations(array $plan, string $country, ?string $asn, string $since, string $until): array
    {
        $base = rtrim((string) config('ooni.api_url', 'https://api.ooni.org'), '/');
        $timeout = (int) config('ooni.timeout', 12);

        $params = [
            'test_name' => 'web_connectivity',
            'probe_cc'  => $country,
            'since'     => $since,
            'until'     => $until,
        ];
        if ($asn) {
            // OONI wants the numeric part only. ip-api.com hands back `AS25513`.
            $params['probe_asn'] = preg_replace('/^AS/i', '', $asn);
        }

        try {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($row) => $pool
                    ->timeout($timeout)
                    ->acceptJson()
                    ->withUserAgent('Larastory-VPN-MiniApp/1.0 (+OONI summary)')
                    ->get($base . '/api/v1/aggregation', array_merge($params, ['input' => $row['url']])),
                $plan,
            ));
        } catch (\Throwable $e) {
            Log::warning('OONI: pool request threw', ['error' => $e->getMessage()]);
            return [];
        }

        $out = [];
        $emptyCount = 0;
        $sampled = false;
        foreach ($responses as $i => $resp) {
            if ($resp instanceof \Throwable) {
                Log::info('OONI: single-URL fetch failed', [
                    'url'   => $plan[$i]['url'] ?? null,
                    'error' => $resp->getMessage(),
                ]);
                $out[$i] = null;
                continue;
            }
            if (!$resp->successful()) {
                Log::info('OONI: HTTP not successful', [
                    'url' => $plan[$i]['url'] ?? null,
                    'status' => $resp->status(),
                ]);
                $out[$i] = null;
                continue;
            }
            if (!$sampled) {
                Log::info('OONI: sample response', [
                    'url' => $plan[$i]['url'] ?? null,
                    'params' => $params,
                    'status' => $resp->status(),
                    'body_head' => substr((string) $resp->body(), 0, 400),
                ]);
                $sampled = true;
            }
            $payload = $resp->json();
            $counts = $this->extractCounts($payload);
            $out[$i] = $counts;
            if (!$counts || (int) ($counts['measurement_count'] ?? 0) === 0) {
                $emptyCount++;
            }
        }
        if ($emptyCount === count($plan) && count($plan) > 0) {
            Log::info('OONI: all URLs returned zero measurements', [
                'country' => $country,
                'asn' => $asn,
                'since' => $since,
                'until' => $until,
            ]);
        }
        return $out;
    }

    /**
     * OONI's /aggregation historically returned `{ result: [{counts}] }` for a
     * no-axis query. Modern versions have been seen returning `{ result: {counts} }`
     * (object, not list), or a multi-row list if a default axis gets applied.
     * Be permissive: sum every row that has a measurement_count field.
     */
    private function extractCounts(mixed $payload): ?array
    {
        if (!is_array($payload)) {
            return null;
        }

        $rows = [];

        if (isset($payload['result'])) {
            $r = $payload['result'];
            if (is_array($r) && isset($r['measurement_count'])) {
                $rows[] = $r;
            } elseif (is_array($r)) {
                foreach ($r as $row) {
                    if (is_array($row) && isset($row['measurement_count'])) {
                        $rows[] = $row;
                    }
                }
            }
        } elseif (isset($payload['measurement_count'])) {
            $rows[] = $payload;
        }

        if (!$rows) {
            return null;
        }

        $sum = [
            'measurement_count' => 0,
            'ok_count' => 0,
            'anomaly_count' => 0,
            'confirmed_count' => 0,
            'failure_count' => 0,
        ];
        foreach ($rows as $row) {
            foreach ($sum as $k => $_) {
                $sum[$k] += (int) ($row[$k] ?? 0);
            }
        }
        return $sum;
    }

    /**
     * Counts of opted-in community probe results per service for the
     * current lookback window. Returns empty map if the table doesn't
     * exist yet (migration not run) — keeps service resilient.
     *
     * @return array<string, array{blocked:int,reachable:int}>
     */
    private function communityCountsByService(string $country, ?string $asn, int $lookbackDays): array
    {
        if (!Schema::hasTable('community_probe_signals')) {
            return [];
        }

        $since = Carbon::now('UTC')->subDays($lookbackDays);
        $q = CommunityProbeSignal::query()
            ->where('country_code', $country)
            ->where('observed_at', '>=', $since);
        if ($asn) {
            $q->where('asn', strtoupper($asn));
        }
        $rows = $q->selectRaw('service_key, result, COUNT(*) as n')
            ->groupBy('service_key', 'result')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->service_key] ??= ['blocked' => 0, 'reachable' => 0];
            $out[$r->service_key][$r->result] = (int) $r->n;
        }
        return $out;
    }

    private function classify(int $measurements, int $confirmed, int $anomaly): string
    {
        $min = (int) config('ooni.min_measurements', 3);
        if ($confirmed > 0) {
            return 'blocked';
        }
        if ($measurements < $min) {
            return 'unknown';
        }
        $ratio = $measurements > 0 ? $anomaly / $measurements : 0.0;
        if ($ratio >= 0.5) {
            return 'blocked';
        }
        if ($ratio >= 0.2) {
            return 'degraded';
        }
        return 'reachable';
    }
}
