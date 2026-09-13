@extends('layouts.app')
@section('content')

<div x-data="{
        instruments: [],
        toggleInstrument(name) {
            this.instruments.includes(name)
                ? this.instruments = this.instruments.filter(i => i !== name)
                : this.instruments.push(name);
        }
     }"
     class="max-w-3xl mx-auto bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 p-8">

    <h1 class="text-2xl font-bold mb-1">Create Your Song</h1>
    <p class="text-sm text-gray-500 mb-6">Fill in the details and let AI Song Studio generate lyrics, music, and vocals.</p>

    <form method="POST" action="{{ route('songs.store') }}" class="space-y-6">
        @csrf

        <div>
            <label class="block text-sm font-medium mb-1">Song Title</label>
            <input name="title" required value="{{ old('title') }}" placeholder="Tere Baad"
                   class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 focus:ring-indigo-500">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Describe your song</label>
            <textarea name="description" rows="2" placeholder="A slow emotional Urdu-Hindi romantic song about separation."
                      class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">{{ old('description') }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Lyrics (optional — leave blank to auto-generate)</label>
            <textarea name="lyrics" rows="4" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">{{ old('lyrics') }}</textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Language</label>
                <select name="language" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                    <option value="urdu">Urdu</option>
                    <option value="hindi">Hindi</option>
                    <option value="roman_urdu">Roman Urdu</option>
                    <option value="punjabi">Punjabi</option>
                    <option value="english">English</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Genre</label>
                <input name="genre" required placeholder="90s Bollywood Romantic" value="{{ old('genre') }}"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Mood</label>
                <input name="mood" required placeholder="Sad Romantic" value="{{ old('mood') }}"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Vocal</label>
                <select name="vocal_type" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="duet">Duet</option>
                    <option value="instrumental">Instrumental only</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Voice Style</label>
                <input name="voice_style" placeholder="Classic playback singer" value="{{ old('voice_style') }}"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Tempo (BPM)</label>
                <input type="number" name="tempo_bpm" min="40" max="220" placeholder="82" value="{{ old('tempo_bpm') }}"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-2">Instruments</label>
            <div class="flex flex-wrap gap-2">
                @foreach (['Harmonium','Sarangi','Tabla','Dholak','Acoustic Guitar','Sitar','Flute','Strings'] as $inst)
                    <button type="button" @click="toggleInstrument('{{ $inst }}')"
                            :class="instruments.includes('{{ $inst }}') ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-800'"
                            class="px-3 py-1.5 rounded-full text-sm border border-gray-300 dark:border-gray-700">
                        {{ $inst }}
                    </button>
                @endforeach
            </div>
            <template x-for="inst in instruments" :key="inst">
                <input type="hidden" name="instruments[]" :value="inst">
            </template>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Song Length (seconds)</label>
            <input type="number" name="duration" min="15" max="360" value="{{ old('duration', 180) }}"
                   class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
        </div>

        <button type="submit" class="w-full py-3 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold">
            Generate Song
        </button>
    </form>
</div>
@endsection
