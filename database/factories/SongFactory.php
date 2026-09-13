<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SongFactory extends Factory
{
    protected $model = \App\Models\Song::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'language' => 'english',
            'genre' => 'pop',
            'mood' => 'happy',
            'vocal_type' => 'male',
            'tempo_bpm' => 100,
            'instruments' => ['guitar', 'drums'],
            'status' => 'pending',
        ];
    }
}
