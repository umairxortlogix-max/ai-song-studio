@extends('layouts.app')
@section('content')

<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    @foreach ([
        'Total Songs' => $stats['total'],
        'Completed' => $stats['completed'],
        'Processing' => $stats['processing'],
        'Failed' => $stats['failed'],
        'Remaining Today' => $stats['remaining_today'],
    ] as $label => $value)
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4">
            <div class="text-2xl font-bold">{{ $value }}</div>
            <div class="text-xs text-gray-500">{{ $label }}</div>
        </div>
    @endforeach
</div>

<div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold">My Songs</h2>
    <a href="{{ route('songs.create') }}" class="text-sm text-indigo-600 hover:underline">+ Create New Song</a>
</div>

<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800 text-left text-gray-500">
            <tr>
                <th class="p-3">Title</th>
                <th class="p-3">Genre</th>
                <th class="p-3">Duration</th>
                <th class="p-3">Status</th>
                <th class="p-3">Created</th>
                <th class="p-3">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($songs as $song)
                <tr>
                    <td class="p-3 font-medium">{{ $song->title }}</td>
                    <td class="p-3">{{ $song->genre }}</td>
                    <td class="p-3">{{ $song->duration ? gmdate('i:s', $song->duration) : '—' }}</td>
                    <td class="p-3">
                        <span @class([
                            'px-2 py-0.5 rounded-full text-xs',
                            'bg-green-100 text-green-700' => $song->status === 'completed',
                            'bg-yellow-100 text-yellow-700' => in_array($song->status, ['pending','processing']),
                            'bg-red-100 text-red-700' => $song->status === 'failed',
                        ])>{{ ucfirst($song->status) }}</span>
                    </td>
                    <td class="p-3">{{ $song->created_at->diffForHumans() }}</td>
                    <td class="p-3 space-x-2">
                        <a href="{{ route('songs.show', $song) }}" class="text-indigo-600 hover:underline">Open</a>
                        @if ($song->status === 'completed')
                            <a href="{{ route('songs.download', [$song, 'mp3']) }}" class="text-indigo-600 hover:underline">MP3</a>
                        @endif
                        <form action="{{ route('songs.destroy', $song) }}" method="POST" class="inline" onsubmit="return confirm('Delete this song?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-6 text-center text-gray-400">No songs yet — create your first one!</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $songs->links() }}</div>
@endsection
