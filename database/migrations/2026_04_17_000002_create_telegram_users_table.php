<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_id')->primary();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('language_code', 8)->default('ru');
            $table->string('photo_url')->nullable();
            $table->boolean('allows_write_to_pm')->default(true);
            $table->string('sub_token', 64)->nullable()->unique();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->index('last_active_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_users');
    }
};
