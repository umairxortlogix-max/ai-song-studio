<?php

namespace App\Jobs;

use App\AI\DTOs\LyricsRequest;
use App\AI\Exceptions\AllProvidersUnavailableException;
use App\AI\Services\AiRouterService;
use App\Models\Lyric;
use App\Models\Song;
use App\Models\SongGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateLyricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // fallback across providers happens inside AiRouterService, not via job retries
    public int $timeout = 180;

    public function __construct(public readonly int $songId, public readonly int $generationId) {}

    public function handle(AiRouterService $router): void
    {
        $song = Song::findOrFail($this->songId);
        $generation = SongGeneration::findOrFail($this->generationId);

        $generation->update(['stage' => 'processing', 'status' => 'processing', 'started_at' => $generation->started_at ?? now()]);

        // Lyrics the user already typed in themselves don't need generation.
        if (filled($song->lyrics)) {
            $generation->update(['stage' => 'lyrics_generated', 'progress' => 20]);
            GenerateMusicJob::dispatch($song->id, $generation->id);
            return;
        }

        try {
            $result = $router->attempt(
                operation: 'generate_lyrics',
                request: new LyricsRequest(
                    theme: $song->description ?: $song->title,
                    language: $song->language,
                    genre: $song->genre,
                    mood: $song->mood,
                    title: $song->title,
                ),
                userId: $song->user_id,
            );

            $song->update(['lyrics' => $result->lyrics]);

            Lyric::where('song_id', $song->id)->update(['is_current' => false]);
            Lyric::create([
                'song_id' => $song->id,
                'content' => $result->lyrics,
                'version' => $song->lyricsVersions()->count() + 1,
                'is_current' => true,
            ]);

            $generation->update(['stage' => 'lyrics_generated', 'progress' => 20]);

            GenerateMusicJob::dispatch($song->id, $generation->id);
        } catch (AllProvidersUnavailableException $e) {
            $this->fail($song, $generation, 'All available AI generation providers are currently unavailable. Please try again later.');
        }
    }

    private function fail(Song $song, SongGeneration $generation, string $message): void
    {
        $song->update(['status' => 'failed']);
        $generation->update(['status' => 'failed', 'stage' => 'failed', 'error_message' => $message, 'completed_at' => now()]);
    }
}
