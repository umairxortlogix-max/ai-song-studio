<?php

namespace Tests\Feature;

use App\Models\Song;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
