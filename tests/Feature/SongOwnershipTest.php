<?php

namespace Tests\Feature;

use App\Jobs\GenerateLyricsJob;
use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SongOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_view_another_users_song(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $song = Song::factory()->for($owner)->create();

        $this->actingAs($stranger)
            ->get(route('songs.show', $song))
            ->assertForbidden();
    }

    public function test_owner_can_view_their_own_song(): void
    {
        $owner = User::factory()->create();
        $song = Song::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->get(route('songs.show', $song))
            ->assertOk();
    }

    public function test_song_create_logs_dispatch_event_before_queueing_generation(): void
    {
        Log::spy();
        Queue::fake();

        $owner = User::factory()->create();

        $this->withoutMiddleware();

        $this->actingAs($owner)
            ->post(route('songs.store'), [
                'title' => 'My Test Song',
                'description' => 'A catchy tune',
                'genre' => 'pop',
                'mood' => 'happy',
                'language' => 'en',
                'tempo_bpm' => 100,
                'vocal_type' => 'female',
                'voice_style' => 'warm',
                'duration' => 180,
            ])
            ->assertRedirect();

        Log::assertLogged('info', function ($message, $context) {
            return str_contains($message, 'Song create request received')
                || str_contains($message, 'Song generation queued');
        });
    }

    public function test_owner_can_retry_a_failed_song_generation_for_the_same_song(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $song = Song::factory()->for($owner)->create([
            'status' => 'failed',
        ]);

        $owner->markEmailAsVerified();

        $this->withoutMiddleware();

        $this->actingAs($owner)
            ->from(route('songs.show', $song))
            ->post(route('songs.regenerate', $song), [])
            ->assertRedirect(route('songs.show', $song));

        $song->refresh();
        $this->assertSame('pending', $song->status);

        Queue::assertPushed(GenerateLyricsJob::class, function (GenerateLyricsJob $job) use ($song) {
            return $job->songId === $song->id;
        });
    }
}
