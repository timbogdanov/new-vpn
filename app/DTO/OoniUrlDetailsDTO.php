<?php

namespace App\DTO;

use Carbon\Carbon;

readonly class OoniUrlDetailsDTO
{
    /**
     * @param  array<int, OoniTimeseriesPointDTO>  $timeseries
     * @param  array<int, OoniAsnBreakdownDTO>  $asnBreakdown
     * @param  array<int, OoniMeasurementDTO>  $measurements
     * @param  array<int, OoniCountryBreakdownDTO>  $countryBreakdown
     * @param  array{totalChecks:int,blockedChecks:int,okChecks:int,confirmedBlocks:int,failureChecks:int,windowDays:int,blockPercent:int,trendDirection:string}  $aggregated
     */
    public function __construct(
        public string $url,
        public string $host,
        public string $urlHash,
        public string $countryCode,
        public ?string $asn,
        public ?string $asnName,
        public int $lookbackDays,
        public string $verdictStatus,
        public string $verdictReason,
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
        public array $countryBreakdown = [],
        public array $aggregated = [],
        public Carbon $freshAt = new Carbon(),
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
            'countryBreakdown' => array_map(fn ($c) => $c->toArray(), $this->countryBreakdown),
            'recentMeasurements' => array_map(fn ($m) => $m->toArray(), $this->measurements),
            'aggregated' => $this->aggregated,
            'freshAt' => $this->freshAt->toIso8601String(),
        ];
    }
}
