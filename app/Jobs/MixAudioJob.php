<?php

namespace App\Jobs;

use App\Models\AudioFile;
use App\Models\Song;
use App\Models\SongGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Mixes the instrumental + vocals tracks into a single final MP3/WAV
 * using FFmpeg. If the chosen provider already returned a fully mixed
 * track (finalPath), this job just normalizes/transcodes it instead
 * of re-mixing.
 */
class MixAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(public readonly int $songId, public readonly int $generationId) {}

    public function handle(): void
    {
        $song = Song::findOrFail($this->songId);
        $generation = SongGeneration::findOrFail($this->generationId);
        $generation->update(['stage' => 'mixing', 'progress' => 85]);

        $instrumental = $song->audioFiles()->where('type', 'instrumental')->latest()->first();
        $vocals = $song->audioFiles()->where('type', 'vocals')->latest()->first();

        $outputRelative = sprintf('songs/%s/%s/%s/%s/final.mp3', now()->format('Y'), now()->format('m'), $song->user_id, $song->id);

        $outputAbsolute = Storage::disk('public')->path($outputRelative);
        @mkdir(dirname($outputAbsolute), 0755, true);

        try {
            if ($vocals && $instrumental) {
                $this->mixTwoTracks(
                    Storage::disk('public')->path($instrumental->path),
                    Storage::disk('public')->path($vocals->path),
                    $outputAbsolute
                );
            } elseif ($instrumental) {
                // Instrumental-only song: just transcode/copy to the canonical final path.
                $this->transcode(Storage::disk('public')->path($instrumental->path), $outputAbsolute);
            } else {
                throw new \RuntimeException('No audio tracks available to mix.');
            }

            $wavRelative = str_replace('.mp3', '.wav', $outputRelative);
            $this->transcode($outputAbsolute, Storage::disk('public')->path($wavRelative), 'wav');

            AudioFile::create([
                'song_id' => $song->id,
                'type' => 'final',
                'path' => $outputRelative,
                'format' => 'mp3',
            ]);

            $song->update(['final_audio' => $outputRelative, 'final_audio_wav' => $wavRelative]);

            FinalizeSongJob::dispatch($song->id, $generation->id);
        } catch (\Throwable $e) {
            Log::error("MixAudioJob failed for song {$song->id}: {$e->getMessage()}");
            $song->update(['status' => 'failed']);
            $generation->update([
                'status' => 'failed',
                'stage' => 'failed',
                'error_message' => 'Audio mixing failed. Please try again.',
                'completed_at' => now(),
            ]);
        }
    }

    private function mixTwoTracks(string $instrumentalPath, string $vocalsPath, string $outputPath): void
    {
        // ffmpeg -i instrumental.mp3 -i vocals.mp3 -filter_complex amix=inputs=2:duration=longest -c:a libmp3lame -q:a 2 output.mp3
        $process = new Process([
            'ffmpeg', '-y',
            '-i', $instrumentalPath,
            '-i', $vocalsPath,
            '-filter_complex', 'amix=inputs=2:duration=longest:dropout_transition=2',
            '-c:a', 'libmp3lame', '-q:a', '2',
            $outputPath,
        ]);
        $process->setTimeout(240);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('ffmpeg mix failed: ' . $process->getErrorOutput());
        }
    }

    private function transcode(string $inputPath, string $outputPath, string $format = 'mp3'): void
    {
        $codec = $format === 'wav' ? 'pcm_s16le' : 'libmp3lame';

        $process = new Process(['ffmpeg', '-y', '-i', $inputPath, '-c:a', $codec, $outputPath]);
        $process->setTimeout(180);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('ffmpeg transcode failed: ' . $process->getErrorOutput());
        }
    }
}
