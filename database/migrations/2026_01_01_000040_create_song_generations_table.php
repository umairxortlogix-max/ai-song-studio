<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('song_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained('songs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->string('current_model')->nullable();
            $table->string('stage')->default('pending');
            // pending|processing|lyrics_generated|music_generated|vocals_generated|mixing|completed|failed
            $table->unsignedTinyInteger('progress')->default(0); // 0-100
            $table->unsignedInteger('attempt')->default(0);
            $table->unsignedInteger('max_attempts')->default(4); // one per provider in the chain
            $table->string('status')->default('pending'); // pending|processing|completed|failed
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['song_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_generations');
    }
};
