<?php

namespace App\DTO;

readonly class OoniServiceVerdictDTO
{
    public function __construct(
        public string $key,
        public string $label,
        public string $status,       // 'reachable' | 'degraded' | 'blocked' | 'unknown'
        public int $measurementCount,
        public int $confirmedCount,
        public int $anomalyCount,
        public int $okCount,
        public ?string $lastChangeAt, // ISO8601 or null
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'status' => $this->status,
            'measurements' => $this->measurementCount,
            'confirmed' => $this->confirmedCount,
            'anomaly' => $this->anomalyCount,
            'ok' => $this->okCount,
            'lastChangeAt' => $this->lastChangeAt,
        ];
    }
}
