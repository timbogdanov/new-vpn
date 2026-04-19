<?php

namespace App\DTO;

readonly class OoniAsnBreakdownDTO
{
    public function __construct(
        public string $asn,           // 'AS25513'
        public ?string $asnName,
        public ?string $friendlyName = null,   // 'MTS' — from config('ooni.asn_friendly')
        public ?string $networkType = null,    // mobile|broadband|cdn|cloud|transit|null
        public int $measurementCount = 0,
        public int $okCount = 0,
        public int $anomalyCount = 0,
        public int $confirmedCount = 0,
        public int $failureCount = 0,
        public string $status = 'unknown',     // reachable | degraded | blocked | unknown
    ) {}

    public function toArray(): array
    {
        return [
            'asn' => $this->asn,
            'asnName' => $this->asnName,
            'friendlyName' => $this->friendlyName,
            'networkType' => $this->networkType,
            'measurements' => $this->measurementCount,
            'ok' => $this->okCount,
            'anomaly' => $this->anomalyCount,
            'confirmed' => $this->confirmedCount,
            'failure' => $this->failureCount,
            'status' => $this->status,
        ];
    }
}
