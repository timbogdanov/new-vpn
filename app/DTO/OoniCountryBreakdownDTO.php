<?php

namespace App\DTO;

readonly class OoniCountryBreakdownDTO
{
    public function __construct(
        public string $countryCode,           // 'RU'
        public int $measurementCount,
        public int $okCount,
        public int $anomalyCount,
        public int $confirmedCount,
        public int $failureCount,
        public string $status,                // reachable | degraded | blocked | unknown
        public bool $isRegional,              // true if this is in the user's regional_peers list
    ) {}

    public function toArray(): array
    {
        return [
            'countryCode' => $this->countryCode,
            'measurements' => $this->measurementCount,
            'ok' => $this->okCount,
            'anomaly' => $this->anomalyCount,
            'confirmed' => $this->confirmedCount,
            'failure' => $this->failureCount,
            'status' => $this->status,
            'isRegional' => $this->isRegional,
        ];
    }
}
