<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->timestamp('trial_used_at')->nullable()->after('last_active_at');
            $table->timestamp('onboarded_at')->nullable()->after('trial_used_at');
            $table->text('admin_notes')->nullable()->after('onboarded_at');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropColumn(['trial_used_at', 'onboarded_at', 'admin_notes']);
        });
    }
};
