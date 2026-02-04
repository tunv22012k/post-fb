<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Facebook Scheduler</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-5xl mx-auto py-10 px-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">System Dashboard</h1>
                <p class="text-gray-500 mt-1">Scalable Scheduler / Phase 1: MVP</p>
            </div>
            
            <a href="{{ route('posts.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg shadow-md transition">
                + Create New Post
            </a>
        </div>

        <!-- System Alerts -->
        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow" role="alert">
                <p class="font-bold">Success</p>
                <p>{{ session('success') }}</p>
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow" role="alert">
                <p class="font-bold">Error</p>
                <p>{{ session('error') }}</p>
            </div>
        @endif

        <!-- Connected Pages Card -->
        <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <span class="mr-2">📢</span> Connected Pages
                </h2>
                
                <a href="{{ route('facebook.login') }}" class="flex items-center bg-[#1877F2] hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.791-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    Connect with Facebook
                </a>
            </div>

            @if(count($channels) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm whitespace-nowrap">
                        <thead class="uppercase tracking-wider border-b-2 border-gray-200 bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-4 font-medium text-gray-500">Page Name</th>
                                <th scope="col" class="px-6 py-4 font-medium text-gray-500">Platform ID</th>
                                <th scope="col" class="px-6 py-4 font-medium text-gray-500">Token Status</th>
                                <th scope="col" class="px-6 py-4 font-medium text-gray-500">Connected At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($channels as $channel)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 font-medium text-gray-900">{{ $channel->name }}</td>
                                <td class="px-6 py-4 text-gray-500">{{ $channel->platform_id }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Active (Permanent)
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500">{{ \Carbon\Carbon::parse($channel->created_at)->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                    <p class="text-gray-500 mb-2">No pages connected yet.</p>
                    <p class="text-sm text-gray-400">Click "Connect with Facebook" to bulk import your pages.</p>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
