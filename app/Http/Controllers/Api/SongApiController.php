<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSongRequest;
use App\Jobs\GenerateLyricsJob;
use App\Jobs\GenerateMusicJob;
use App\Jobs\GenerateVocalsJob;
use App\Models\Song;
use App\Models\SongGeneration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SongApiController extends Controller
{
    public function store(StoreSongRequest $request): JsonResponse
    {
        $song = Song::create([
            'user_id' => Auth::id(),
            ...$request->validated(),
            'status' => 'pending',
        ]);

        return response()->json(['song' => $song], 201);
    }

    private function newGeneration(Song $song): SongGeneration
    {
        return SongGeneration::create([
            'song_id' => $song->id,
            'user_id' => $song->user_id,
            'stage' => 'pending',
            'status' => 'pending',
        ]);
    }

    public function generateLyrics(Song $song): JsonResponse
    {
        $this->authorize('update', $song);
        $generation = $this->newGeneration($song);
        GenerateLyricsJob::dispatch($song->id, $generation->id);

        return response()->json(['message' => 'Lyrics generation queued.', 'generation_id' => $generation->id]);
    }

    public function generateMusic(Song $song): JsonResponse
    {
        $this->authorize('update', $song);
        $generation = $this->newGeneration($song);
        GenerateMusicJob::dispatch($song->id, $generation->id);

        return response()->json(['message' => 'Music generation queued.', 'generation_id' => $generation->id]);
    }

    public function generateVocals(Song $song): JsonResponse
    {
        $this->authorize('update', $song);
        $generation = $this->newGeneration($song);
        GenerateVocalsJob::dispatch($song->id, $generation->id);

        return response()->json(['message' => 'Vocals generation queued.', 'generation_id' => $generation->id]);
    }

    public function generateFull(Song $song): JsonResponse
    {
        $this->authorize('update', $song);
        $generation = $this->newGeneration($song);
        GenerateLyricsJob::dispatch($song->id, $generation->id); // chains through the full pipeline

        return response()->json(['message' => 'Full song generation queued.', 'generation_id' => $generation->id]);
    }

    public function regenerate(Song $song): JsonResponse
    {
        $this->authorize('update', $song);
        $song->update(['status' => 'pending']);
        $generation = $this->newGeneration($song);
        GenerateLyricsJob::dispatch($song->id, $generation->id);

        return response()->json(['message' => 'Regeneration queued.', 'generation_id' => $generation->id]);
    }

    public function status(Song $song): JsonResponse
    {
        $this->authorize('view', $song);
        $generation = $song->latestGeneration;

        return response()->json([
            'stage' => $generation?->stage,
            'status' => $generation?->status,
            'progress' => $generation?->progress ?? 0,
            'error_message' => $generation?->error_message,
        ]);
    }

    public function download(Song $song, string $format)
    {
        $this->authorize('download', $song);
        abort_unless(in_array($format, ['mp3', 'wav']), 404);
        $path = $format === 'mp3' ? $song->final_audio : $song->final_audio_wav;
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->download($path, $song->slug . ".{$format}");
    }

    public function providers(): JsonResponse
    {
        // Public-safe view: no API keys, just names/status/priority.
        $providers = \App\Models\AiProvider::where('is_active', true)
            ->orderBy('priority')
            ->get(['name', 'slug', 'status', 'priority']);

        return response()->json(['providers' => $providers]);
    }
}
