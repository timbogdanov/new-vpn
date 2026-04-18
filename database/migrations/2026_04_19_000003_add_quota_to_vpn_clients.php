<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vpn_clients', function (Blueprint $table) {
            // null = unlimited; numeric value enforced by billing:enforce-quotas.
            $table->unsignedBigInteger('quota_bytes')->nullable()->after('last_traffic_down');
            $table->unsignedBigInteger('quota_bytes_used_cached')->default(0)->after('quota_bytes');
            $table->timestamp('expires_at')->nullable()->after('quota_bytes_used_cached');
            $table->string('disabled_reason', 64)->nullable()->after('expires_at');

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('vpn_clients', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['quota_bytes', 'quota_bytes_used_cached', 'expires_at', 'disabled_reason']);
        });
    }
};
