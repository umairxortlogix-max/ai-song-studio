<?php

namespace App\Listeners;

use App\Events\SongGenerationCompleted;
use App\Notifications\SongReadyNotification;

class NotifyUserSongCompleted
{
    public function handle(SongGenerationCompleted $event): void
    {
        $event->song->user->notify(new SongReadyNotification($event->song));
    }
}
