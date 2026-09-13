<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lyric extends Model
{
    protected $table = 'lyrics';

    protected $fillable = ['song_id', 'provider_id', 'content', 'version', 'is_current'];

    protected function casts(): array
    {
        return ['is_current' => 'boolean'];
    }

    public function song()
    {
        return $this->belongsTo(Song::class);
    }
}
