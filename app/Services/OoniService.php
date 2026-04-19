<?php

namespace App\Services;

use App\DTO\OoniAsnBreakdownDTO;
use App\DTO\OoniMeasurementDTO;
use App\DTO\OoniServiceVerdictDTO;
use App\DTO\OoniSummaryDTO;
use App\DTO\OoniTimeseriesPointDTO;
use App\DTO\OoniUrlDetailsDTO;
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
            ->whereNull('deleted_at')
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

    public function classify(int $measurements, int $confirmed, int $anomaly): string
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

    /**
     * Same as summary() but sorted by verdict severity so the FreedomMap grid
     * shows the most-likely-blocked services first. Underlying fetch path and
     * cache key are shared with summary() — only the order is different.
     */
    public function topBlocked(string $countryCode, ?string $asn, ?int $limit = null): OoniSummaryDTO
    {
        $summary = $this->summary($countryCode, $asn);
        $limit ??= (int) config('ooni.top_blocked_display_limit', 15);

        $rank = [
            'blocked'   => 0,
            'degraded'  => 1,
            'unknown'   => 2,
            'reachable' => 3,
        ];

        $services = $summary->services;
        usort($services, function (OoniServiceVerdictDTO $a, OoniServiceVerdictDTO $b) use ($rank) {
            $r = ($rank[$a->status] ?? 9) <=> ($rank[$b->status] ?? 9);
            if ($r !== 0) {
                return $r;
            }
            $ratioA = $a->measurementCount > 0 ? ($a->anomalyCount + $a->confirmedCount) / $a->measurementCount : 0.0;
            $ratioB = $b->measurementCount > 0 ? ($b->anomalyCount + $b->confirmedCount) / $b->measurementCount : 0.0;
            if ($ratioA !== $ratioB) {
                return $ratioB <=> $ratioA;
            }
            return $b->measurementCount <=> $a->measurementCount;
        });

        if ($limit > 0 && count($services) > $limit) {
            $services = array_slice($services, 0, $limit);
        }

        return new OoniSummaryDTO(
            countryCode: $summary->countryCode,
            asn: $summary->asn,
            asnName: $summary->asnName,
            lookbackDays: $summary->lookbackDays,
            services: $services,
            freshAt: $summary->freshAt,
        );
    }

    /**
     * Rich per-URL details: day-by-day timeseries + per-ASN breakdown within
     * the country + recent measurement records, for a details page. Cached
     * per (url, country, asn, days) for config('ooni.details_cache_ttl').
     */
    public function urlDetails(
        string $normalizedUrl,
        string $countryCode,
        ?string $asn,
        ?int $days = null,
        bool $force = false,
    ): OoniUrlDetailsDTO {
        $country = strtoupper(trim($countryCode)) ?: 'XX';
        $asnUp   = $asn ? strtoupper(trim($asn)) : null;
        $days    = $days ?? (int) config('ooni.details_lookback_days', 30);
        $days    = max(7, min(60, $days));

        $hash = sha1($normalizedUrl);
        $cacheKey = "ooni:url:{$hash}:{$country}:" . ($asnUp ?: 'NOASN') . ":{$days}";
        $ttl = (int) config('ooni.details_cache_ttl', 900);

        if ($force) {
            Cache::forget($cacheKey);
        } else {
            $cached = Cache::get($cacheKey);
            if ($cached instanceof OoniUrlDetailsDTO) {
                return $cached;
            }
        }

        $built = $this->buildUrlDetails($normalizedUrl, $hash, $country, $asnUp, $days);
        Cache::put($cacheKey, $built, $ttl);
        return $built;
    }

    private function buildUrlDetails(
        string $url,
        string $hash,
        string $country,
        ?string $asn,
        int $days,
    ): OoniUrlDetailsDTO {
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        $since = Carbon::now('UTC')->subDays($days)->toDateString();
        $until = Carbon::now('UTC')->addDay()->toDateString();
        $asnNumeric = $asn ? preg_replace('/^AS/i', '', $asn) : null;

        [$timeseriesPayload, $asnPayload, $measurementsPayload] = $this->poolDetailRequests(
            $url, $country, $asnNumeric, $since, $until,
        );

        $timeseries = $this->extractTimeseries($timeseriesPayload, $days);
        $asnBreakdown = $this->extractAsnBreakdown($asnPayload);
        $measurements = $this->extractMeasurements($measurementsPayload);

        // Degraded-confidence fallback: if ASN-scoped timeseries is thin, retry
        // country-only so the sparkline isn't empty.
        $degradedConfidence = false;
        $totalMeasurements = array_sum(array_map(fn ($p) => $p->measurementCount, $timeseries));
        if ($asn && $totalMeasurements < (int) config('ooni.min_measurements', 3)) {
            $degradedConfidence = true;
            $fallback = $this->fetchCountryTimeseries($url, $country, $since, $until);
            if ($fallback) {
                $timeseries = $this->extractTimeseries($fallback, $days);
                $totalMeasurements = array_sum(array_map(fn ($p) => $p->measurementCount, $timeseries));
            }
        }

        $totalOk = array_sum(array_map(fn ($p) => $p->okCount, $timeseries));
        $totalAnomaly = array_sum(array_map(fn ($p) => $p->anomalyCount, $timeseries));
        $totalConfirmed = array_sum(array_map(fn ($p) => $p->confirmedCount, $timeseries));
        $totalFailure = array_sum(array_map(fn ($p) => $p->failureCount, $timeseries));

        [$status, $reason] = $this->classifyWithReason(
            $totalMeasurements,
            $totalConfirmed,
            $totalAnomaly,
        );

        $communityCount = $this->communityCountForUrl($url, $country, $asn, $days);
        if ($status === 'unknown' && $communityCount > 0) {
            // Mirror the soft-upgrade pattern from buildSummary().
            $reason = 'community_only';
        }

        return new OoniUrlDetailsDTO(
            url: $url,
            host: $host,
            urlHash: $hash,
            countryCode: $country,
            asn: $asn,
            asnName: null,
            lookbackDays: $days,
            verdictStatus: $status,
            verdictReason: $reason,
            measurementCount: $totalMeasurements,
            confirmedCount: $totalConfirmed,
            anomalyCount: $totalAnomaly,
            okCount: $totalOk,
            failureCount: $totalFailure,
            communityCount: $communityCount,
            degradedConfidence: $degradedConfidence,
            recommendedServerSlug: null, // controller populates from ServerRegistry
            timeseries: $timeseries,
            asnBreakdown: $asnBreakdown,
            measurements: $measurements,
            freshAt: Carbon::now(),
        );
    }

    /**
     * Three parallel requests: daily timeseries, per-ASN breakdown, recent
     * measurement records. Returns payloads in a fixed order.
     *
     * @return array{0: ?array, 1: ?array, 2: ?array}
     */
    private function poolDetailRequests(
        string $url,
        string $country,
        ?string $asnNumeric,
        string $since,
        string $until,
    ): array {
        $base = rtrim((string) config('ooni.api_url', 'https://api.ooni.org'), '/');
        $timeout = (int) config('ooni.timeout', 12);
        $measurementsLimit = (int) config('ooni.details_measurements_limit', 20);

        $common = [
            'test_name' => 'web_connectivity',
            'probe_cc'  => $country,
            'since'     => $since,
            'until'     => $until,
            'input'     => $url,
        ];
        if ($asnNumeric) {
            $common['probe_asn'] = $asnNumeric;
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($base, $timeout, $common, $url, $country, $asnNumeric, $measurementsLimit) {
                $requests = [];

                // 0: daily timeseries (ASN-scoped when provided)
                $requests[] = $pool
                    ->timeout($timeout)
                    ->acceptJson()
                    ->withUserAgent('Larastory-VPN-MiniApp/1.0 (+OONI url details)')
                    ->get($base . '/api/v1/aggregation', array_merge($common, ['axis_x' => 'measurement_start_day']));

                // 1: per-ASN breakdown within country (no probe_asn filter)
                $asnParams = $common;
                unset($asnParams['probe_asn']);
                $asnParams['axis_x'] = 'probe_asn';
                $requests[] = $pool
                    ->timeout($timeout)
                    ->acceptJson()
                    ->withUserAgent('Larastory-VPN-MiniApp/1.0 (+OONI url details)')
                    ->get($base . '/api/v1/aggregation', $asnParams);

                // 2: recent measurements
                $mParams = [
                    'test_name' => 'web_connectivity',
                    'probe_cc'  => $country,
                    'input'     => $url,
                    'limit'     => $measurementsLimit,
                    'order'     => 'desc',
                    'order_by'  => 'measurement_start_time',
                ];
                if ($asnNumeric) {
                    $mParams['probe_asn'] = $asnNumeric;
                }
                $requests[] = $pool
                    ->timeout($timeout)
                    ->acceptJson()
                    ->withUserAgent('Larastory-VPN-MiniApp/1.0 (+OONI url details)')
                    ->get($base . '/api/v1/measurements', $mParams);

                return $requests;
            });
        } catch (\Throwable $e) {
            Log::warning('OONI: url-details pool threw', ['error' => $e->getMessage(), 'url' => $url]);
            return [null, null, null];
        }

        $out = [null, null, null];
        foreach ($responses as $i => $resp) {
            if ($resp instanceof \Throwable) {
                Log::info('OONI: url-details single request failed', ['i' => $i, 'error' => $resp->getMessage()]);
                continue;
            }
            if (!method_exists($resp, 'successful') || !$resp->successful()) {
                continue;
            }
            $out[$i] = $resp->json();
        }
        return $out;
    }

    /**
     * @return array<int, OoniTimeseriesPointDTO>
     */
    private function extractTimeseries(?array $payload, int $days): array
    {
        $byDate = [];
        $rows = (is_array($payload) && is_array($payload['result'] ?? null)) ? $payload['result'] : [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['measurement_start_day'])) {
                continue;
            }
            $date = (string) $row['measurement_start_day'];
            $byDate[$date] = [
                'm'    => (int) ($row['measurement_count'] ?? 0),
                'ok'   => (int) ($row['ok_count'] ?? 0),
                'anom' => (int) ($row['anomaly_count'] ?? 0),
                'conf' => (int) ($row['confirmed_count'] ?? 0),
                'fail' => (int) ($row['failure_count'] ?? 0),
            ];
        }

        $points = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now('UTC')->subDays($i)->toDateString();
            $b = $byDate[$date] ?? ['m' => 0, 'ok' => 0, 'anom' => 0, 'conf' => 0, 'fail' => 0];
            $points[] = new OoniTimeseriesPointDTO(
                date: $date,
                measurementCount: $b['m'],
                okCount: $b['ok'],
                anomalyCount: $b['anom'],
                confirmedCount: $b['conf'],
                failureCount: $b['fail'],
            );
        }
        return $points;
    }

    /**
     * @return array<int, OoniAsnBreakdownDTO>
     */
    private function extractAsnBreakdown(?array $payload): array
    {
        $rows = (is_array($payload) && is_array($payload['result'] ?? null)) ? $payload['result'] : [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['probe_asn'])) {
                continue;
            }
            $m = (int) ($row['measurement_count'] ?? 0);
            $conf = (int) ($row['confirmed_count'] ?? 0);
            $anom = (int) ($row['anomaly_count'] ?? 0);
            $status = $this->classify($m, $conf, $anom);
            $asnNum = (int) $row['probe_asn'];
            if ($asnNum <= 0) {
                continue;
            }
            $out[] = new OoniAsnBreakdownDTO(
                asn: 'AS' . $asnNum,
                asnName: null,
                measurementCount: $m,
                okCount: (int) ($row['ok_count'] ?? 0),
                anomalyCount: $anom,
                confirmedCount: $conf,
                failureCount: (int) ($row['failure_count'] ?? 0),
                status: $status,
            );
        }

        $rank = ['blocked' => 0, 'degraded' => 1, 'unknown' => 2, 'reachable' => 3];
        usort($out, function (OoniAsnBreakdownDTO $a, OoniAsnBreakdownDTO $b) use ($rank) {
            $r = ($rank[$a->status] ?? 9) <=> ($rank[$b->status] ?? 9);
            if ($r !== 0) return $r;
            return $b->measurementCount <=> $a->measurementCount;
        });

        $limit = (int) config('ooni.asn_breakdown_limit', 8);
        return $limit > 0 ? array_slice($out, 0, $limit) : $out;
    }

    /**
     * @return array<int, OoniMeasurementDTO>
     */
    private function extractMeasurements(?array $payload): array
    {
        $rows = (is_array($payload) && is_array($payload['results'] ?? null)) ? $payload['results'] : [];
        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $asnInt = (int) ($r['probe_asn'] ?? 0);
            $out[] = new OoniMeasurementDTO(
                measurementUid: $r['measurement_uid'] ?? null,
                reportId: $r['report_id'] ?? null,
                probeAsn: $asnInt > 0 ? 'AS' . $asnInt : null,
                probeCc: $r['probe_cc'] ?? null,
                measurementStartTime: $r['measurement_start_time'] ?? null,
                anomaly: (bool) ($r['anomaly'] ?? false),
                confirmed: (bool) ($r['confirmed'] ?? false),
                failure: (bool) ($r['failure'] ?? false),
                measurementUrl: $r['measurement_url'] ?? null,
                testName: $r['test_name'] ?? null,
            );
        }
        return $out;
    }

    /**
     * @return array{0: string, 1: string}  [status, reasonCode]
     */
    private function classifyWithReason(int $measurements, int $confirmed, int $anomaly): array
    {
        $min = (int) config('ooni.min_measurements', 3);
        if ($confirmed > 0) {
            return ['blocked', 'confirmed_block'];
        }
        if ($measurements < $min) {
            return ['unknown', 'no_data'];
        }
        $ratio = $measurements > 0 ? $anomaly / $measurements : 0.0;
        if ($ratio >= 0.5) {
            return ['blocked', 'high_anomaly'];
        }
        if ($ratio >= 0.2) {
            return ['degraded', 'partial_anomaly'];
        }
        return ['reachable', 'reachable_strong'];
    }

    private function fetchCountryTimeseries(string $url, string $country, string $since, string $until): ?array
    {
        $base = rtrim((string) config('ooni.api_url', 'https://api.ooni.org'), '/');
        $timeout = (int) config('ooni.timeout', 12);
        try {
            $resp = Http::timeout($timeout)
                ->acceptJson()
                ->withUserAgent('Larastory-VPN-MiniApp/1.0 (+OONI url details fallback)')
                ->get($base . '/api/v1/aggregation', [
                    'test_name' => 'web_connectivity',
                    'probe_cc'  => $country,
                    'input'     => $url,
                    'since'     => $since,
                    'until'     => $until,
                    'axis_x'    => 'measurement_start_day',
                ]);
            return $resp->successful() ? $resp->json() : null;
        } catch (\Throwable $e) {
            Log::info('OONI: country-fallback timeseries failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function communityCountForUrl(string $url, string $country, ?string $asn, int $lookbackDays): int
    {
        if (!Schema::hasTable('community_probe_signals')) {
            return 0;
        }
        $since = Carbon::now('UTC')->subDays($lookbackDays);
        $q = CommunityProbeSignal::query()
            ->whereNull('deleted_at')
            ->where('country_code', $country)
            ->where('url', $url)
            ->where('observed_at', '>=', $since);
        if ($asn) {
            $q->where('asn', strtoupper($asn));
        }
        return (int) $q->count();
    }
}
