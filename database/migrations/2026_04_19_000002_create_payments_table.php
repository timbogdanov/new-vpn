<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_user_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('plan_key', 64);
            $table->unsignedInteger('stars_amount');
            $table->string('currency', 8)->default('XTR');
            $table->string('telegram_payment_charge_id', 128)->nullable()->unique();
            $table->string('provider_payment_charge_id', 128)->nullable();
            $table->string('invoice_payload', 128)->unique();
            $table->json('raw_payload')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->foreign('telegram_user_id')
                ->references('telegram_id')->on('telegram_users')
                ->cascadeOnDelete();

            $table->foreign('subscription_id')
                ->references('id')->on('subscriptions')
                ->nullOnDelete();

            $table->index(['telegram_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
