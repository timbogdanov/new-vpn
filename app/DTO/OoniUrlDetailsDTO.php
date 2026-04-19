<?php

namespace App\DTO;

use Carbon\Carbon;

readonly class OoniUrlDetailsDTO
{
    /**
     * @param  array<int, OoniTimeseriesPointDTO>  $timeseries
     * @param  array<int, OoniAsnBreakdownDTO>  $asnBreakdown
     * @param  array<int, OoniMeasurementDTO>  $measurements
     */
    public function __construct(
        public string $url,
        public string $host,
        public string $urlHash,
        public string $countryCode,
        public ?string $asn,
        public ?string $asnName,
        public int $lookbackDays,
        public string $verdictStatus,   // reachable|degraded|blocked|unknown
        public string $verdictReason,   // confirmed_block|high_anomaly|partial_anomaly|no_data|community_only|reachable_strong
        public int $measurementCount,
        public int $confirmedCount,
        public int $anomalyCount,
        public int $okCount,
        public int $failureCount,
        public int $communityCount,
        public bool $degradedConfidence,
        public ?string $recommendedServerSlug,
        public array $timeseries,
        public array $asnBreakdown,
        public array $measurements,
        public Carbon $freshAt,
    ) {}

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'host' => $this->host,
            'urlHash' => $this->urlHash,
            'countryCode' => $this->countryCode,
            'asn' => $this->asn,
            'asnName' => $this->asnName,
            'lookbackDays' => $this->lookbackDays,
            'verdictStatus' => $this->verdictStatus,
            'verdictReason' => $this->verdictReason,
            'measurements' => $this->measurementCount,
            'confirmed' => $this->confirmedCount,
            'anomaly' => $this->anomalyCount,
            'ok' => $this->okCount,
            'failure' => $this->failureCount,
            'communityCount' => $this->communityCount,
            'degradedConfidence' => $this->degradedConfidence,
            'recommendedServerSlug' => $this->recommendedServerSlug,
            'timeseries' => array_map(fn ($p) => $p->toArray(), $this->timeseries),
            'asnBreakdown' => array_map(fn ($a) => $a->toArray(), $this->asnBreakdown),
            'recentMeasurements' => array_map(fn ($m) => $m->toArray(), $this->measurements),
            'freshAt' => $this->freshAt->toIso8601String(),
        ];
    }
}
