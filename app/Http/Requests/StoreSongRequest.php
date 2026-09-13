<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // any authenticated user (route middleware handles auth)
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'lyrics' => ['nullable', 'string', 'max:8000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'language' => ['required', 'string', 'in:urdu,hindi,english,punjabi,roman_urdu'],
            'genre' => ['required', 'string', 'max:80'],
            'mood' => ['required', 'string', 'max:80'],
            'vocal_type' => ['required', 'string', 'in:male,female,duet,instrumental'],
            'voice_style' => ['nullable', 'string', 'max:80'],
            'tempo_bpm' => ['nullable', 'integer', 'min:40', 'max:220'],
            'instruments' => ['nullable', 'array'],
            'instruments.*' => ['string', 'max:60'],
            'duration' => ['nullable', 'integer', 'min:15', 'max:360'],
        ];
    }
}
