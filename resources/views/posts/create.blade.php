<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post - Facebook Scheduler</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-xl w-full bg-white rounded-xl shadow-2xl p-8">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-gray-800">Schedule New Post</h1>
            <p class="text-gray-500 mt-2">Multiplexing to <span class="font-bold text-blue-600">{{ $count }} Connected Pages</span></p>
        </div>

        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                <p>{{ session('error') }}</p>
            </div>
        @endif

        <form action="{{ route('posts.store') }}" method="POST">
            @csrf
            
            <div class="mb-6">
                <label for="content" class="block mb-2 text-sm font-medium text-gray-700">Content / Article URL</label>
                <textarea id="content" name="content" rows="6" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" placeholder="Write something or paste a link..." required></textarea>
                <p class="mt-2 text-xs text-gray-400">This content will be processed by the System (Master -> Variants -> Fan-out).</p>
            </div>

            <div class="mb-8">
                <label for="scheduled_at" class="block mb-2 text-sm font-medium text-gray-700">Schedule Date & Time</label>
                <input type="datetime-local" id="scheduled_at" name="scheduled_at" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" required>
            </div>

            <div class="flex items-center justify-between space-x-4">
                <a href="{{ route('dashboard') }}" class="w-1/3 block text-center py-3 px-4 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="w-2/3 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg hover:shadow-xl transition transform hover:-translate-y-0.5">
                    🚀 Schedule to {{ $count }} Pages
                </button>
            </div>
        </form>
    </div>
</body>
</html>
