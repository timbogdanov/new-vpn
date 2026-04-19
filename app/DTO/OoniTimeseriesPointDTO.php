<?php

namespace App\DTO;

readonly class OoniTimeseriesPointDTO
{
    public function __construct(
        public string $date, // YYYY-MM-DD
        public int $measurementCount,
        public int $okCount,
        public int $anomalyCount,
        public int $confirmedCount,
        public int $failureCount,
    ) {}

    public function anomalyRatio(): float
    {
        return $this->measurementCount > 0
            ? ($this->anomalyCount + $this->confirmedCount) / $this->measurementCount
            : 0.0;
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'measurements' => $this->measurementCount,
            'ok' => $this->okCount,
            'anomaly' => $this->anomalyCount,
            'confirmed' => $this->confirmedCount,
            'failure' => $this->failureCount,
            'anomalyRatio' => round($this->anomalyRatio(), 4),
        ];
    }
}
