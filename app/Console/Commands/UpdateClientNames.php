<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateClientNames extends Command
{
    protected $signature = 'clients:update-names {--dry-run}';
    protected $description = 'Deprecated: legacy one-shot migration to rename 3x-ui client emails.';

    public function handle(): int
    {
        $this->warn('`clients:update-names` is deprecated after the Mini App migration.');
        $this->line('If you need the old behaviour, see git history before the Mini App commit.');
        return 0;
    }
}
