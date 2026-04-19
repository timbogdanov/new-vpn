<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->json('ooni_watchlist_urls')->nullable();
            $table->timestamp('ooni_contribute_acked_at')->nullable();
        });

        Schema::table('community_probe_signals', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
            $table->index(
                ['user_hash', 'deleted_at', 'observed_at'],
                'signals_user_tombstone_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('community_probe_signals', function (Blueprint $table) {
            $table->dropIndex('signals_user_tombstone_idx');
            $table->dropColumn('deleted_at');
        });

        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropColumn(['ooni_watchlist_urls', 'ooni_contribute_acked_at']);
        });
    }
};
