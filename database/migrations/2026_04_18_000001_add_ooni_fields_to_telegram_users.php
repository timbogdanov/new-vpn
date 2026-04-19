<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->json('ooni_watchlist')->nullable();
            $table->json('ooni_watchlist_snapshot')->nullable();
            $table->boolean('ooni_contribute')->default(false);
            $table->string('ooni_last_country', 2)->nullable();
            $table->string('ooni_last_asn', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropColumn([
                'ooni_watchlist',
                'ooni_watchlist_snapshot',
                'ooni_contribute',
                'ooni_last_country',
                'ooni_last_asn',
            ]);
        });
    }
};
