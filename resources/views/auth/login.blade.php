@extends('layouts.app')
@section('content')
<div class="max-w-sm mx-auto bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8">
    <h1 class="text-xl font-bold mb-6">Log in</h1>
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <input type="email" name="email" placeholder="Email" required class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
        <input type="password" name="password" placeholder="Password" required class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember"> Remember me</label>
        <button class="w-full py-2 rounded-lg bg-indigo-600 text-white">Log in</button>
    </form>
    <div class="mt-4 text-sm flex justify-between">
        <a href="{{ route('register') }}" class="text-indigo-600 hover:underline">Create account</a>
        <a href="{{ route('password.request') }}" class="text-indigo-600 hover:underline">Forgot password?</a>
    </div>
</div>
@endsection
