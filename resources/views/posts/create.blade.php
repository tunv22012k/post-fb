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
            <p class="text-gray-500 mt-2">Create content for your <span class="font-bold text-blue-600">{{ $count }} Connected Pages</span></p>
        </div>

        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                <p>{{ session('error') }}</p>
            </div>
        @endif

        <form action="{{ route('posts.store') }}" method="POST" enctype="multipart/form-data" id="postForm">
            @csrf
            
            <!-- Content Input -->
            <div class="mb-6">
                <label for="content" class="block mb-2 text-sm font-bold text-gray-700">1. Content / Article URL</label>
                <textarea id="content" name="content" rows="4" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500" placeholder="What's on your mind?..." required></textarea>
            </div>

            <!-- Media -->
            <div class="mb-6">
                <label for="image" class="block mb-2 text-sm font-medium text-gray-700">Image (Optional - Watermark auto-added per channel)</label>
                <input type="file" id="image" name="image" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>

            <!-- Advanced Distribution Builder -->
            <div class="mb-8">
                <label class="block mb-2 text-sm font-bold text-gray-700">2. Distribution Strategy</label>
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <div id="distribution-rows" class="space-y-4">
                        <!-- Dynamic Rows will appear here -->
                    </div>

                    <button type="button" onclick="addGroup()" class="mt-4 flex items-center text-blue-600 font-bold hover:text-blue-800">
                        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Add Distribution Group
                    </button>
                    
                    <div class="mt-2 text-xs text-gray-500">
                        * Channels must be assigned to a group to be scheduled.
                    </div>
                </div>
            </div>

            <!-- Hidden Input for JSON Data -->
            <input type="hidden" name="distribution_config" id="distribution_config">

            <div class="mb-8">
                <label for="scheduled_at" class="block mb-2 text-sm font-medium text-gray-700">Schedule Date & Time</label>
                <input type="datetime-local" id="scheduled_at" name="scheduled_at" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" required>
            </div>

            <div class="flex items-center justify-between space-x-4">
                <a href="{{ route('dashboard') }}" class="w-1/3 block text-center py-3 px-4 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="button" onclick="submitForm()" class="w-2/3 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg hover:shadow-xl transition transform hover:-translate-y-0.5">
                    🚀 Schedule
                </button>
            </div>
        </form>

    </div>

    <script>
        // Data from Controller
        const allChannels = @json($channels);
        let groups = [];
        
        function init() {
            // Add initial empty group
            addGroup();
        }

        function addGroup() {
            const id = Date.now();
            groups.push({
                id: id,
                channels: [],
                use_ai: false,
                language: 'vi',
                styles: []
            });
            render();
        }

        function removeGroup(id) {
            groups = groups.filter(g => g.id !== id);
            render();
        }

        function updateGroup(id, field, value) {
            const group = groups.find(g => g.id === id);
            if (group) {
                group[field] = value;
                
                // Logic: If styles count > channels count, trim styles? 
                // Requirement: "If group has 3 channels, only allow 3 styles max".
                if (field === 'styles') {
                    if (group.styles.length > group.channels.length) {
                         alert(`Max styles allowed for this group is ${group.channels.length} (1 per channel).`);
                         // Revert or trim
                         group.styles = value.slice(0, group.channels.length);
                    }
                }
                render();
            }
        }

        function render() {
            const container = document.getElementById('distribution-rows');
            container.innerHTML = '';
            
            // Calculate available channels (not used in other groups)
            // But actually, user might want to drag/drop. simpler complexity:
            // Just show all channels in every group, but visual indicator if used?
            // Or better: Filter out used channels?
            // Let's filter used channels to prevent double posting.
            
            const usedChannelIds = groups.flatMap(g => g.channels.map(id => String(id)));

            groups.forEach((group, index) => {
                const el = document.createElement('div');
                el.className = 'bg-white p-4 rounded-lg shadow-sm border border-gray-200';
                
                // Header
                let html = `
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="font-bold text-gray-700">Group ${index + 1}</h3>
                        <button type="button" onclick="removeGroup(${group.id})" class="text-red-500 text-sm hover:underline">Remove</button>
                    </div>
                `;

                // Channel Selector
                html += `
                    <div class="mb-3">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Select Channels</label>
                        <div class="flex flex-wrap gap-2">`;
                
                allChannels.forEach(c => {
                    const channelIdStr = String(c.id);
                    const isSelected = group.channels.includes(channelIdStr);
                    const isUsedElsewhere = !isSelected && usedChannelIds.includes(channelIdStr);
                    
                    if (!isUsedElsewhere) {
                        html += `
                            <div onclick="toggleChannel(${group.id}, '${c.id}')" 
                                 class="cursor-pointer select-none inline-flex items-center px-3 py-1 rounded-full border transition
                                 ${isSelected ? 'bg-blue-500 border-blue-600 text-white font-semibold' : 'bg-gray-100 border-gray-300 text-gray-600 hover:bg-gray-200'}">
                                ${isSelected ? '<svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>' : ''}
                                ${c.name}
                            </div>
                        `;
                    }
                });

                html += `</div>
                        ${group.channels.length === 0 ? '<p class="text-xs text-red-500 mt-1">Please select at least one channel.</p>' : ''}
                    </div>`;

                // AI Toggle
                html += `
                    <div class="flex items-center mb-3 bg-gray-50 p-2 rounded">
                        <input type="checkbox" id="ai_${group.id}" 
                            onchange="updateGroup(${group.id}, 'use_ai', this.checked)"
                            ${group.use_ai ? 'checked' : ''}
                            class="w-4 h-4 text-blue-600 rounded">
                        <label for="ai_${group.id}" class="ml-2 text-sm font-medium text-gray-700">Enable AI Content Factory</label>
                    </div>
                `;

                // AI Options
                if (group.use_ai) {
                    html += `
                        <div class="pl-4 border-l-2 border-blue-200 space-y-3">
                            <div>
                                <label class="block text-xs text-gray-500 uppercase">Language</label>
                                <select onchange="updateGroup(${group.id}, 'language', this.value)" class="mt-1 block w-full py-1 px-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="vi" ${group.language === 'vi' ? 'selected' : ''}>Vietnamese</option>
                                    <option value="en" ${group.language === 'en' ? 'selected' : ''}>English</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 uppercase mb-1">Styles (Max ${group.channels.length})</label>
                                <div class="flex flex-wrap gap-2">`;
                                
                    ['KOL', 'Expert', 'GenZ', 'Fun', 'Formal'].forEach(style => {
                        const isChecked = group.styles.includes(style);
                        const isDisabled = !isChecked && group.styles.length >= group.channels.length;
                        
                        html += `
                            <label class="inline-flex items-center">
                                <input type="checkbox" value="${style}" 
                                    onchange="toggleStyle(${group.id}, '${style}')"
                                    ${isChecked ? 'checked' : ''}
                                    ${isDisabled ? 'disabled' : ''}
                                    class="rounded text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 disabled:opacity-50">
                                <span class="ml-2 text-sm text-gray-700 ${isDisabled ? 'opacity-50' : ''}">${style}</span>
                            </label>
                        `;
                    });

                    html += `   </div>
                                <p class="text-xs text-gray-400 mt-1">Select up to ${group.channels.length} styles. If you select fewer styles than channels, styles will be reused (but content will be unique).</p>
                            </div>
                        </div>
                    `;
                }

                el.innerHTML = html;
                container.appendChild(el);
            });
        }

        function toggleChannel(groupId, channelId) {
            const channelIdStr = String(channelId);
            const group = groups.find(g => g.id === groupId);
            const idx = group.channels.indexOf(channelIdStr);
            if (idx > -1) {
                group.channels.splice(idx, 1);
            } else {
                group.channels.push(channelIdStr);
            }
            // Reset styles if channel count drops
            if (group.styles.length > group.channels.length) {
                group.styles = group.styles.slice(0, group.channels.length);
            }
            render();
        }

        function toggleStyle(groupId, style) {
            const group = groups.find(g => g.id === groupId);
            const idx = group.styles.indexOf(style);
            if (idx > -1) {
                group.styles.splice(idx, 1);
            } else {
                if (group.styles.length < group.channels.length) {
                    group.styles.push(style);
                }
            }
            render();
        }

        function submitForm() {
            // Validate: check if any group has empty channels
            if (groups.some(g => g.channels.length === 0)) {
                alert("Please assign at least one channel to each group.");
                return;
            }
            if (groups.length === 0) {
                 alert("Please add at least one distribution group.");
                 return;
            }

            document.getElementById('distribution_config').value = JSON.stringify(groups);
            document.getElementById('postForm').submit();
        }

        init();
    </script>
    </div>
</body>
</html>
