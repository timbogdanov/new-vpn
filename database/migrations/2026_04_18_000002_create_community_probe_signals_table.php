<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_probe_signals', function (Blueprint $table) {
            $table->id();
            // sha256 HMAC of telegram_id — never store raw IDs in signal rows.
            $table->string('user_hash', 64)->index();
            $table->string('country_code', 2);
            $table->string('asn', 16)->nullable();
            $table->string('service_key', 32);
            $table->string('url', 512);
            $table->string('result', 16); // 'reachable' | 'blocked'
            $table->timestamp('observed_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['country_code', 'asn', 'service_key', 'observed_at'],
                'signals_lookup_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_probe_signals');
    }
};
