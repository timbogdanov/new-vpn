<?php

namespace App\DTO;

readonly class OoniMeasurementDTO
{
    public function __construct(
        public ?string $measurementUid,
        public ?string $reportId,
        public ?string $probeAsn,
        public ?string $probeCc,
        public ?string $measurementStartTime,
        public bool $anomaly,
        public bool $confirmed,
        public bool $failure,
        public ?string $measurementUrl,
        public ?string $testName,
    ) {}

    public function toArray(): array
    {
        return [
            'measurementUid' => $this->measurementUid,
            'reportId' => $this->reportId,
            'probeAsn' => $this->probeAsn,
            'probeCc' => $this->probeCc,
            'measurementStartTime' => $this->measurementStartTime,
            'anomaly' => $this->anomaly,
            'confirmed' => $this->confirmed,
            'failure' => $this->failure,
            'measurementUrl' => $this->measurementUrl,
            'testName' => $this->testName,
        ];
    }
}
