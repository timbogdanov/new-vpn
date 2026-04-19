<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('telegram_users')->update(['ooni_contribute' => true]);

        // Flip the column default so new users come in opted-in.
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE telegram_users ALTER COLUMN ooni_contribute SET DEFAULT TRUE');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE telegram_users ALTER COLUMN ooni_contribute SET DEFAULT 1');
        }
        // SQLite can't alter column defaults in-place. New users funnel through
        // TelegramAuthService::upsert(), which now explicitly sets the column
        // to true on creation, so the model-level default matches regardless.
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE telegram_users ALTER COLUMN ooni_contribute SET DEFAULT FALSE');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE telegram_users ALTER COLUMN ooni_contribute SET DEFAULT 0');
        }
    }
};
