<?php

namespace App\Notifications;

use App\Models\Song;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SongReadyNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Song $song) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your song "' . $this->song->title . '" is ready!')
            ->line('Your AI-generated song has finished processing.')
            ->action('Listen now', route('songs.show', $this->song));
    }

    public function toArray($notifiable): array
    {
        return [
            'song_id' => $this->song->id,
            'title' => $this->song->title,
            'message' => 'Your song "' . $this->song->title . '" is ready!',
        ];
    }
}
