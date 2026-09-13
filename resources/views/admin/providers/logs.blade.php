@extends('layouts.app')
@section('content')
<h1 class="text-xl font-bold mb-4">Usage Logs — {{ $provider->name }}</h1>
<div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800 text-left text-gray-500">
            <tr><th class="p-3">Time</th><th class="p-3">Operation</th><th class="p-3">Status</th><th class="p-3">Response Time</th><th class="p-3">Error</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($logs as $log)
                <tr>
                    <td class="p-3">{{ $log->created_at }}</td>
                    <td class="p-3">{{ $log->operation }}</td>
                    <td class="p-3">{{ $log->status }}</td>
                    <td class="p-3">{{ $log->response_time }}ms</td>
                    <td class="p-3 text-red-600">{{ $log->error_message }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
