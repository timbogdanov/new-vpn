<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_user_id');
            $table->unsignedBigInteger('server_id');
            $table->string('uuid', 36);
            $table->string('sub_id', 32);
            $table->string('email');
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('last_traffic_up')->default(0);
            $table->unsignedBigInteger('last_traffic_down')->default(0);
            $table->timestamp('last_traffic_synced_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('telegram_user_id')->references('telegram_id')->on('telegram_users')->cascadeOnDelete();
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();

            $table->unique(['telegram_user_id', 'server_id', 'deleted_at']);
            $table->index('telegram_user_id');
            $table->index('server_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_clients');
    }
};
