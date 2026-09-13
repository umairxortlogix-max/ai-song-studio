<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('lyrics')->nullable();
            $table->string('language');
            $table->string('genre');
            $table->string('mood');
            $table->string('vocal_type'); // male|female|duet|instrumental
            $table->unsignedInteger('tempo_bpm')->nullable();
            $table->json('instruments')->nullable();
            $table->text('description')->nullable();
            $table->string('voice_style')->nullable();
            $table->unsignedInteger('duration')->nullable(); // seconds
            $table->string('status')->default('pending'); // see song_generations for granular status
            $table->string('cover_image')->nullable();
            $table->string('final_audio')->nullable(); // path to final mixed mp3
            $table->string('final_audio_wav')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
