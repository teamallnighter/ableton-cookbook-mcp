<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Save Changes - {{ config('app.name', 'Ableton Cookbook') }}</title>

    <!-- Fonts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/teamallnighter/abletonSans@latest/abletonSans.css">

    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-ZK491B502K"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-ZK491B502K');
    </script>


    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased" style="background-color: #C3C3C3;">
    <!-- Navigation -->
    <nav class="shadow-sm border-b-2" style="background-color: #0D0D0D; border-color: #01CADA;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center">
                        <span class="text-xl font-bold" style="color: #ffdf00;">🎵 Ableton Cookbook</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Progress Indicator -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <div class="flex items-center justify-center mb-8">
            <div class="flex items-center space-x-4">
                <!-- Step 1: Upload (Complete) -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-vibrant-green flex items-center justify-center text-black font-bold">
                        ✓
                    </div>
                    <span class="ml-2 text-black font-semibold">Upload</span>
                </div>
                
                <!-- Connector -->
                <div class="w-16 h-1 bg-vibrant-green"></div>
                
                <!-- Step 2: Annotate (Complete) -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-vibrant-green flex items-center justify-center text-black font-bold">
                        ✓
                    </div>
                    <span class="ml-2 text-black font-semibold">Annotate</span>
                </div>
                
                <!-- Connector -->
                <div class="w-16 h-1 bg-vibrant-green"></div>
                
                <!-- Step 3: Details (Active) -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-vibrant-green flex items-center justify-center text-black font-bold">
                        3
                    </div>
                    <span class="ml-2 text-black font-semibold">Details</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        <div class="card card-body">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold mb-2 text-black">Publish Your Rack</h1>
                <p class="text-gray-600">Add the final details and share with the community</p>
            </div>

            @if ($errors->any())
                <div class="mb-6 p-4 bg-vibrant-red/10 border border-vibrant-red rounded-lg">
                    <div class="text-vibrant-red font-medium mb-2">Please fix the following errors:</div>
                    <ul class="text-vibrant-red text-sm list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('racks.update', $rack) }}" class="space-y-6">
                @method('PUT')
                @csrf

                <!-- Rack Summary -->
                <div class="bg-gray-50 rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-bold text-black mb-4">Rack Summary</h2>
                    <div class="grid md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <div class="font-medium text-gray-700">Type</div>
                            <div class="text-black">{{ ucfirst(str_replace('GroupDevice', ' Rack', $rack->rack_type)) }}</div>
                        </div>
                        <div>
                            <div class="font-medium text-gray-700">Chains</div>
                            <div class="text-black">{{ $rack->chain_count ?? count($rack->chains ?? []) }}</div>
                        </div>
                        <div>
                            <div class="font-medium text-gray-700">Devices</div>
                            <div class="text-black">{{ $rack->device_count ?? 0 }}</div>
                        </div>
                        <div>
                            <div class="font-medium text-gray-700">File Size</div>
                            <div class="text-black">{{ number_format($rack->file_size / 1024, 1) }} KB</div>
                        </div>
                    </div>
                </div>

                <!-- Rack Title -->
                <div class="mb-6">
                    <label for="title" class="block text-sm font-medium mb-2 text-black">
                        Rack Title <span class="text-vibrant-red">*</span>
                    </label>
                    <input type="text" 
                           id="title" 
                           name="title" 
                           value="{{ old('title', $rack->title) }}" 
                           maxlength="255"
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vibrant-green focus:border-vibrant-green">
                    @error('title')
                        <p class="mt-2 text-sm text-vibrant-red font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Category -->
                <div class="mb-6">
                    <label for="category" class="block text-sm font-medium mb-2 text-black">
                        Category <span class="text-vibrant-red">*</span>
                    </label>
                    <select name="category" id="category" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vibrant-green focus:border-vibrant-green">
                        <option value="">Select a category</option>
                        @foreach($categories as $key => $label)
                            <option value="{{ $key }}" {{ old('category', $rack->category) === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">
                        Categories are specific to {{ ucfirst(str_replace('GroupDevice', ' Racks', $rack->rack_type)) }}
                    </p>
                    @error('category')
                        <p class="mt-2 text-sm text-vibrant-red font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium mb-2 text-black">
                        Description <span class="text-vibrant-red">*</span>
                    </label>
                    <textarea id="description" 
                              name="description" 
                              rows="4" 
                              maxlength="1000"
                              required
                              placeholder="Describe your rack, how it works, and what makes it special..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vibrant-green focus:border-vibrant-green">{{ old('description', $rack->description) }}</textarea>
                    <div class="flex justify-between mt-1 text-xs text-gray-500">
                        <span>Help others understand when and how to use your rack</span>
                        <span id="desc-count">0/1000</span>
                    </div>
                    @error('description')
                        <p class="mt-2 text-sm text-vibrant-red font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Tags -->
                <div class="mb-6">
                    <label for="tags" class="block text-sm font-medium mb-2 text-black">
                        Tags
                    </label>
                    <input type="text" 
                           id="tags" 
                           name="tags" 
                           value="{{ old('tags', $rack->tags->pluck('name')->implode(', ')) }}" 
                           maxlength="500"
                           placeholder="e.g., vintage, warm, experimental, techno, vocal processing"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vibrant-green focus:border-vibrant-green">
                    <p class="mt-1 text-xs text-gray-500">Separate tags with commas. Help others discover your rack!</p>
                    @error('tags')
                        <p class="mt-2 text-sm text-vibrant-red font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- How-to Guide -->
                <div class="mb-6">
                    <label for="how_to_article" class="block text-sm font-medium mb-2 text-black">
                        How-to Guide
                    </label>
                    <x-markdown-editor 
                        name="how_to_article" 
                        :value="old('how_to_article', $rack->how_to_article ?? '')"
                        placeholder="Explain how to use this rack, what it does, and any special techniques... Use Markdown for formatting!"
                        :rows="12"
                        :maxlength="100000"
                        :showToolbar="true"
                        :showPreview="true"
                        :showImageUpload="false"
                        class="border border-gray-300"
                    />
                    <p class="mt-1 text-xs text-gray-500">Maximum 100,000 characters. Markdown formatting supported. This will help other producers understand how to use your rack effectively.</p>
                    @error('how_to_article')
                        <p class="mt-2 text-sm text-vibrant-red font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Visibility -->
                <div class="mb-6">
                    <div class="flex items-center space-x-3">
                        <input type="hidden" name="is_public" value="0">
                        <input type="checkbox" 
                               id="is_public" 
                               name="is_public" 
                               value="1" 
                               {{ old('is_public', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-vibrant-green focus:ring-vibrant-green">
                        <label for="is_public" class="text-black font-medium">
                            Make this rack public
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 ml-6">Public racks can be discovered and downloaded by the community</p>
                </div>

                <!-- Chain Preview (if annotated) -->
                @if(!empty($rack->chain_annotations))
                    <div class="bg-blue-50 rounded-lg p-6">
                        <h3 class="font-medium text-blue-900 mb-3">📋 Your Chain Names</h3>
                        <div class="space-y-2">
                            @foreach($rack->chain_annotations as $index => $annotation)
                                @if(!empty($annotation['custom_name']))
                                    <div class="flex items-center text-sm">
                                        <div class="w-2 h-2 rounded-full bg-vibrant-purple mr-3"></div>
                                        <span class="text-blue-800">Chain {{ $index + 1 }}: {{ $annotation['custom_name'] }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Action Buttons -->
                <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                    <a href="{{ route('racks.annotate', $rack) }}" 
                       class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        ← Back to Chains
                    </a>

                    <button type="submit" 
                            id="publishBtn"
                            class="px-8 py-3 bg-vibrant-green text-black font-bold rounded-lg hover:opacity-90 transition-opacity">
                        🚀 Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Description character counter
        const descTextarea = document.getElementById('description');
        const descCounter = document.getElementById('desc-count');

        function updateDescCounter() {
            const count = descTextarea.value.length;
            descCounter.textContent = `${count}/1000`;
            descCounter.className = count > 800 ? 'text-orange-500' : 'text-gray-500';
        }

        descTextarea.addEventListener('input', updateDescCounter);
        updateDescCounter();

        // Form submission
        document.querySelector('form').addEventListener('submit', function() {
            const publishBtn = document.getElementById('publishBtn');
            publishBtn.disabled = true;
            publishBtn.innerHTML = '🚀 Publishing...';
        });
    </script>
</body>
</html>
