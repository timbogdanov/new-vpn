<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id')->nullable();
            $table->string('action', 64);
            $table->string('target_type', 64)->nullable();
            $table->string('target_id', 64)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('admin_user_id');
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_log');
    }
};
