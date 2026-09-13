@extends('layouts.app')
@section('content')
<div class="max-w-md mx-auto bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 text-center">
    <h1 class="text-xl font-bold mb-4">Verify your email</h1>
    <p class="text-sm text-gray-500 mb-6">We've sent a verification link to your email address.</p>
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm">Resend verification email</button>
    </form>
</div>
@endsection
