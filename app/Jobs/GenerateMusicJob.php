<?php

namespace App\Jobs;

use App\AI\DTOs\MusicRequest;
use App\AI\Exceptions\AllProvidersUnavailableException;
use App\AI\Services\AiRouterService;
use App\Models\AudioFile;
use App\Models\Song;
use App\Models\SongGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateMusicJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(public readonly int $songId, public readonly int $generationId) {}

    public function handle(AiRouterService $router): void
    {
        $song = Song::findOrFail($this->songId);
        $generation = SongGeneration::findOrFail($this->generationId);

        try {
            $result = $router->attempt(
                operation: 'generate_music',
                request: new MusicRequest(
                    genre: $song->genre,
                    mood: $song->mood,
                    tempoBpm: $song->tempo_bpm,
                    instruments: $song->instruments ?? [],
                    durationSeconds: $song->duration,
                    lyrics: $song->lyrics,
                    description: $song->description,
                ),
                userId: $song->user_id,
            );

            AudioFile::create([
                'song_id' => $song->id,
                'type' => 'instrumental',
                'path' => $result->filePath,
                'format' => 'mp3',
                'duration_seconds' => $result->durationSeconds,
            ]);

            $generation->update(['stage' => 'music_generated', 'progress' => 50]);

            if ($song->vocal_type === 'instrumental') {
                // No vocals needed — skip straight to mixing (which just finalizes the instrumental).
                MixAudioJob::dispatch($song->id, $generation->id);
            } else {
                GenerateVocalsJob::dispatch($song->id, $generation->id);
            }
        } catch (AllProvidersUnavailableException $e) {
            $this->fail($song, $generation);
        }
    }

    private function fail(Song $song, SongGeneration $generation): void
    {
        $song->update(['status' => 'failed']);
        $generation->update(['status' => 'failed', 'stage' => 'failed', 'error_message' => 'All available AI generation providers are currently unavailable. Please try again later.', 'completed_at' => now()]);
    }
}
