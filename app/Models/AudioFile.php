<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AudioFile extends Model
{
    protected $fillable = ['song_id', 'provider_id', 'type', 'path', 'format', 'duration_seconds', 'size_bytes'];

    public function song()
    {
        return $this->belongsTo(Song::class);
    }

    public function url(): string
    {
        return \Storage::disk('public')->url($this->path);
    }
}
