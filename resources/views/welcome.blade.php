<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI Song Studio</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-white min-h-screen flex items-center justify-center">
    <div class="text-center">
        <h1 class="text-4xl font-bold mb-4">🎵 AI Song Studio</h1>
        <p class="text-gray-400 mb-8">Create complete AI-generated songs from a prompt.</p>
        <div class="space-x-4">
            <a href="{{ route('login') }}" class="px-5 py-2 rounded-lg bg-indigo-600">Log in</a>
            <a href="{{ route('register') }}" class="px-5 py-2 rounded-lg border border-gray-700">Sign up</a>
        </div>
    </div>
</body>
</html>
