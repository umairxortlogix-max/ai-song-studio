<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSongRequest;
use App\Jobs\GenerateLyricsJob;
use App\Models\Song;
use App\Models\SongGeneration;
use App\Models\UserUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SongController extends Controller
{
    public function dashboard(): View
    {
        $user = Auth::user();

        $songs = $user->songs()->latest()->paginate(10);

        $stats = [
            'total' => $user->songs()->count(),
            'completed' => $user->songs()->where('status', 'completed')->count(),
            'processing' => $user->songs()->whereIn('status', ['pending', 'processing'])->count(),
            'failed' => $user->songs()->where('status', 'failed')->count(),
            'remaining_today' => max(0, config('ai.user_daily_generation_limit') - $this->todayUsage($user->id)),
        ];

        return view('songs.dashboard', compact('songs', 'stats'));
    }

    public function create(): View
    {
        return view('songs.create');
    }

    public function store(StoreSongRequest $request): RedirectResponse
    {
        $user = Auth::user();

        if ($this->todayUsage($user->id) >= config('ai.user_daily_generation_limit')) {
            return back()->withErrors(['limit' => 'You have reached your daily generation limit. Please try again tomorrow.']);
        }

        $song = Song::create([
            'user_id' => $user->id,
            ...$request->validated(),
            'status' => 'pending',
        ]);

        $generation = SongGeneration::create([
            'song_id' => $song->id,
            'user_id' => $user->id,
            'stage' => 'pending',
            'status' => 'pending',
        ]);

        UserUsage::updateOrCreate(
            ['user_id' => $user->id, 'date' => now()->toDateString()],
            []
        )->increment('generations_count');

        GenerateLyricsJob::dispatch($song->id, $generation->id);

        return redirect()->route('songs.show', $song)
            ->with('status', 'Your song is being generated in the background.');
    }

    public function show(Song $song): View
    {
        $this->authorize('view', $song);

        $song->load(['latestGeneration', 'audioFiles']);

        return view('songs.show', compact('song'));
    }

    public function status(Song $song)
    {
        $this->authorize('view', $song);

        $generation = $song->latestGeneration;

        return response()->json([
            'stage' => $generation?->stage,
            'status' => $generation?->status,
            'progress' => $generation?->progress ?? 0,
            'error_message' => $generation?->error_message,
            'final_audio_url' => $song->final_audio ? Storage::disk('public')->url($song->final_audio) : null,
        ]);
    }

    public function download(Song $song, string $format)
    {
        $this->authorize('download', $song);

        abort_unless(in_array($format, ['mp3', 'wav']), 404);

        $path = $format === 'mp3' ? $song->final_audio : $song->final_audio_wav;

        abort_unless($path && Storage::disk('public')->exists($path), 404, 'File not ready yet.');

        \App\Models\Download::create(['user_id' => $song->user_id, 'song_id' => $song->id, 'format' => $format]);

        return Storage::disk('public')->download($path, $song->slug . ".{$format}");
    }

    public function destroy(Song $song): RedirectResponse
    {
        $this->authorize('delete', $song);

        $song->delete();

        return redirect()->route('dashboard')->with('status', 'Song deleted.');
    }

    public function toggleFavorite(Song $song): RedirectResponse
    {
        $this->authorize('view', $song);

        $song->update(['is_favorite' => ! $song->is_favorite]);

        return back();
    }

    private function todayUsage(int $userId): int
    {
        return UserUsage::where('user_id', $userId)->where('date', now()->toDateString())->value('generations_count') ?? 0;
    }
}
