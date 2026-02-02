<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facebook Post Scheduler</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<div class="w-full max-w-md bg-white p-8 rounded shadow-md">
    <h1 class="text-2xl font-bold mb-6 text-gray-800">Schedule Facebook Post</h1>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('posts.store') }}" method="POST">
        @csrf

        <div class="mb-4">
            <label for="prompt" class="block text-gray-700 text-sm font-bold mb-2">AI Prompt (Optional):</label>
            <textarea name="prompt" id="prompt" rows="2" 
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                placeholder="Ex: Write a funny post about coding..."></textarea>
            <p class="text-xs text-gray-500 mt-1">Leave Content empty if you want AI to generate it from this prompt.</p>
        </div>

        <div class="mb-4">
            <label for="content" class="block text-gray-700 text-sm font-bold mb-2">Post Content:</label>
            <textarea name="content" id="content" rows="4" 
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                placeholder="Direct content or AI output will appear here."></textarea>
        </div>

        <div class="mb-6">
            <label for="schedule_at" class="block text-gray-700 text-sm font-bold mb-2">Schedule Time:</label>
            <input type="datetime-local" name="schedule_at" id="schedule_at"
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <div class="flex items-center justify-between">
            <button type="submit" 
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Schedule Post
            </button>
        </div>
    </form>
</div>

</body>
</html>
