<?php

namespace App\Jobs;

use App\AI\DTOs\VocalsRequest;
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

class GenerateVocalsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(public readonly int $songId, public readonly int $generationId) {}

    public function handle(AiRouterService $router): void
    {
        $song = Song::findOrFail($this->songId);
        $generation = SongGeneration::findOrFail($this->generationId);

        $instrumental = $song->audioFiles()->where('type', 'instrumental')->latest()->first();

        try {
            $result = $router->attempt(
                operation: 'generate_vocals',
                request: new VocalsRequest(
                    lyrics: $song->lyrics ?? '',
                    vocalType: $song->vocal_type,
                    language: $song->language,
                    voiceStyle: $song->voice_style,
                    instrumentalPath: $instrumental?->path,
                ),
                userId: $song->user_id,
            );

            AudioFile::create([
                'song_id' => $song->id,
                'type' => 'vocals',
                'path' => $result->filePath,
                'format' => pathinfo($result->filePath, PATHINFO_EXTENSION) ?: 'mp3',
            ]);

            $generation->update(['stage' => 'vocals_generated', 'progress' => 75]);

            MixAudioJob::dispatch($song->id, $generation->id);
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
