<?php

namespace Database\Seeders;

use App\Models\Server;
use Illuminate\Database\Seeder;

class ServerSeeder extends Seeder
{
    public function run(): void
    {
        $registry = config('servers.registry', []);

        foreach ($registry as $entry) {
            if (!is_array($entry) || empty($entry['slug'])) {
                continue;
            }

            Server::updateOrCreate(
                ['slug' => $entry['slug']],
                array_filter($entry, fn ($v) => $v !== null),
            );
        }
    }
}
