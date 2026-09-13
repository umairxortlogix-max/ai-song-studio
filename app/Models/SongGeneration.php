<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SongGeneration extends Model
{
    protected $fillable = [
        'song_id', 'user_id', 'current_provider_id', 'current_model', 'stage',
        'progress', 'attempt', 'max_attempts', 'status', 'error_message',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function song()
    {
        return $this->belongsTo(Song::class);
    }

    public function provider()
    {
        return $this->belongsTo(AiProvider::class, 'current_provider_id');
    }
}
