<?php

namespace App\DTO;

readonly class OoniAsnBreakdownDTO
{
    public function __construct(
        public string $asn,           // 'AS25513'
        public ?string $asnName,
        public int $measurementCount,
        public int $okCount,
        public int $anomalyCount,
        public int $confirmedCount,
        public int $failureCount,
        public string $status,        // 'reachable' | 'degraded' | 'blocked' | 'unknown'
    ) {}

    public function toArray(): array
    {
        return [
            'asn' => $this->asn,
            'asnName' => $this->asnName,
            'measurements' => $this->measurementCount,
            'ok' => $this->okCount,
            'anomaly' => $this->anomalyCount,
            'confirmed' => $this->confirmedCount,
            'failure' => $this->failureCount,
            'status' => $this->status,
        ];
    }
}
