<?php

namespace App\DTO;

readonly class MyDataDTO
{
    public function __construct(
        public int $totalSignals,
        public ?string $firstSeenAt,
        public ?string $lastSeenAt,
        public int $distinctUrls,
        public int $reachableCount,
        public int $blockedCount,
        public int $distinctNetworks,
        public array $recentSignals,    // each: ['url','host','serviceKey','country','asn','result','observedAt']
        public int $page,
        public int $perPage,
        public int $totalPages,
        public bool $hasMore,
    ) {}

    public function toArray(): array
    {
        return [
            'totalSignals' => $this->totalSignals,
            'firstSeenAt' => $this->firstSeenAt,
            'lastSeenAt' => $this->lastSeenAt,
            'distinctUrls' => $this->distinctUrls,
            'reachableCount' => $this->reachableCount,
            'blockedCount' => $this->blockedCount,
            'distinctNetworks' => $this->distinctNetworks,
            'recentSignals' => $this->recentSignals,
            'page' => $this->page,
            'perPage' => $this->perPage,
            'totalPages' => $this->totalPages,
            'hasMore' => $this->hasMore,
        ];
    }
}
