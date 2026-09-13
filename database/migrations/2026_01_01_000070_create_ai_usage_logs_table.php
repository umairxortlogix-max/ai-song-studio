<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained('song_generations')->nullOnDelete();
            $table->string('operation'); // generate_lyrics|generate_music|generate_vocals|generate_song
            $table->string('model')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->unsignedInteger('credits_used')->nullable();
            $table->string('status'); // success|failed
            $table->text('error_message')->nullable();
            $table->unsignedInteger('response_time')->nullable(); // ms
            $table->timestamp('created_at')->useCurrent();

            $table->index(['provider_id', 'created_at']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
