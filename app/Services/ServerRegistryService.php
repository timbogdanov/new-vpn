<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Collection;

class ServerRegistryService
{
    public function all(): Collection
    {
        return Server::active()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function available(): Collection
    {
        return Server::available()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function findBySlug(string $slug): ?Server
    {
        return Server::where('slug', $slug)->first();
    }

    public function recommended(): ?Server
    {
        return Server::available()
            ->orderByRaw('load_percent IS NULL, load_percent asc')
            ->orderBy('sort_order')
            ->first();
    }
}
