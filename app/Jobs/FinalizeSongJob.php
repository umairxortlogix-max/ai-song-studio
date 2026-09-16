<?php

namespace App\Jobs;

use App\Events\SongGenerationCompleted;
use App\Models\Song;
use App\Models\SongGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizeSongJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public readonly int $songId, public readonly int $generationId) {}

    public function handle(): void
    {
        $song = Song::findOrFail($this->songId);
        $generation = SongGeneration::findOrFail($this->generationId);

        $song->update(['status' => 'completed']);
        $generation->update([
            'stage' => 'completed',
            'status' => 'completed',
            'progress' => 100,
            'completed_at' => now(),
        ]);

        event(new SongGenerationCompleted($song));
    }
}
