<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('country');
            $table->string('country_code', 2);
            $table->string('city')->nullable();
            $table->string('flag_emoji', 16)->nullable();

            $table->string('host');
            $table->integer('port')->default(443);
            $table->string('protocol')->default('vless');
            $table->integer('inbound_id')->default(1);

            $table->string('xui_host')->nullable();
            $table->integer('xui_port')->nullable();
            $table->string('xui_path')->nullable();
            $table->text('xui_username')->nullable();
            $table->text('xui_password')->nullable();

            $table->text('reality_public_key')->nullable();
            $table->string('reality_short_id')->nullable();
            $table->string('reality_sni')->nullable();
            $table->string('reality_fingerprint')->default('chrome');
            $table->string('reality_spider_x')->nullable();

            $table->string('subscription_host')->nullable();
            $table->integer('subscription_port')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_coming_soon')->default(false);
            $table->integer('capacity_clients')->default(500);
            $table->integer('load_percent')->nullable();
            $table->integer('ping_ms')->nullable();
            $table->timestamp('stats_updated_at')->nullable();
            $table->text('last_error')->nullable();

            $table->json('tags')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
