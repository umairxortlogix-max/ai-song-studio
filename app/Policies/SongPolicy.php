<?php

namespace App\Policies;

use App\Models\Song;
use App\Models\User;

/**
 * Users must only access their own generated songs (spec section 18).
 */
class SongPolicy
{
    public function view(User $user, Song $song): bool
    {
        return $user->id === $song->user_id || $user->isAdmin();
    }

    public function update(User $user, Song $song): bool
    {
        return $user->id === $song->user_id;
    }

    public function delete(User $user, Song $song): bool
    {
        return $user->id === $song->user_id || $user->isAdmin();
    }

    public function download(User $user, Song $song): bool
    {
        return $user->id === $song->user_id || $user->isAdmin();
    }
}
