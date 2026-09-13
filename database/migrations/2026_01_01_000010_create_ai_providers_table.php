<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('provider_type'); // maps to config('ai.provider_classes')
            $table->text('api_key')->nullable(); // encrypted, see AiProvider model cast
            $table->string('api_base_url')->nullable();
            $table->string('model')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100); // lower = tried first
            $table->unsignedInteger('daily_limit')->nullable();
            $table->unsignedInteger('monthly_limit')->nullable();
            $table->unsignedInteger('used_today')->default(0);
            $table->unsignedInteger('used_this_month')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->string('status')->default('healthy'); // healthy|limited|rate_limited|quota_exhausted|error|disabled
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
