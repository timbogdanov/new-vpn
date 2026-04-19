<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ooni_url_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('host', 255);
            $table->string('url', 512)->unique();
            $table->string('country_code', 2)->nullable();
            $table->unsignedInteger('popularity')->default(0);
            $table->string('source', 16)->default('seed');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('host');
            $table->index('country_code');
            $table->index(['popularity'], 'ooni_suggestions_pop_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ooni_url_suggestions');
    }
};
