<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_user_id');
            $table->string('plan_key', 64);
            $table->string('tier', 32);
            $table->string('status', 32);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('stars_paid')->nullable();
            $table->string('telegram_payment_charge_id', 128)->nullable()->unique();
            $table->string('telegram_provider_payment_charge_id', 128)->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->foreign('telegram_user_id')
                ->references('telegram_id')->on('telegram_users')
                ->cascadeOnDelete();

            $table->index(['telegram_user_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
