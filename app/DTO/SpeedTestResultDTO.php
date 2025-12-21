<?php

namespace App\DTO;

use Carbon\Carbon;

readonly class SpeedTestResultDTO
{
    public function __construct(
        public float $downloadMbps,
        public float $uploadMbps,
        public float $pingMs,
        public Carbon $testedAt,
    ) {}

    public function getFormattedDownload(): string
    {
        return round($this->downloadMbps, 1) . ' Mbps';
    }

    public function getFormattedUpload(): string
    {
        return round($this->uploadMbps, 1) . ' Mbps';
    }

    public function getFormattedPing(): string
    {
        return round($this->pingMs, 0) . ' ms';
    }

    public function getTestedAgo(): string
    {
        return $this->testedAt->diffForHumans();
    }

    public function toArray(): array
    {
        return [
            'downloadMbps' => $this->downloadMbps,
            'uploadMbps' => $this->uploadMbps,
            'pingMs' => $this->pingMs,
            'testedAt' => $this->testedAt->toIso8601String(),
        ];
    }
}
