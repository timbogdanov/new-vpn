<?php

namespace App\DTO;

readonly class TrafficDataDTO
{
    public function __construct(
        public int $upload,
        public int $download,
        public int $expiryTime = 0,
    ) {}

    public function getUploadGB(): float
    {
        return round($this->upload / (1024 ** 3), 2);
    }

    public function getDownloadGB(): float
    {
        return round($this->download / (1024 ** 3), 2);
    }

    public function getTotalGB(): float
    {
        return $this->getUploadGB() + $this->getDownloadGB();
    }

    public function getUploadMB(): float
    {
        return round($this->upload / (1024 ** 2), 2);
    }

    public function getDownloadMB(): float
    {
        return round($this->download / (1024 ** 2), 2);
    }

    public function getFormattedUpload(): string
    {
        $gb = $this->getUploadGB();
        if ($gb >= 1) {
            return $gb . ' GB';
        }
        return $this->getUploadMB() . ' MB';
    }

    public function getFormattedDownload(): string
    {
        $gb = $this->getDownloadGB();
        if ($gb >= 1) {
            return $gb . ' GB';
        }
        return $this->getDownloadMB() . ' MB';
    }

    public function toArray(): array
    {
        return [
            'upload' => $this->upload,
            'download' => $this->download,
            'uploadGB' => $this->getUploadGB(),
            'downloadGB' => $this->getDownloadGB(),
            'totalGB' => $this->getTotalGB(),
            'expiryTime' => $this->expiryTime,
        ];
    }
}
