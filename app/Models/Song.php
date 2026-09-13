<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Song extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'slug', 'lyrics', 'language', 'genre', 'mood',
        'vocal_type', 'tempo_bpm', 'instruments', 'description', 'voice_style',
        'duration', 'status', 'cover_image', 'final_audio', 'final_audio_wav', 'is_favorite',
    ];

    protected function casts(): array
    {
        return [
            'instruments' => 'array',
            'is_favorite' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Song $song) {
            $song->slug = $song->slug ?: Str::slug($song->title) . '-' . Str::random(6);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function generations()
    {
        return $this->hasMany(SongGeneration::class);
    }

    public function latestGeneration()
    {
        return $this->hasOne(SongGeneration::class)->latestOfMany();
    }

    public function audioFiles()
    {
        return $this->hasMany(AudioFile::class);
    }

    public function lyricsVersions()
    {
        return $this->hasMany(Lyric::class);
    }
}
