<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Edit {{ $rack->title }} - {{ config('app.name', 'Ableton Cookbook') }}</title>

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

                <!-- Auth Links -->
                <div class="flex items-center space-x-4">
                    @auth
                        <a href="{{ route('racks.upload') }}" style="background-color: #01DA48; color: #0D0D0D;" class="px-4 py-2 rounded-lg hover:opacity-90 transition-opacity font-semibold">
                            Upload Rack
                        </a>
                        <a href="{{ route('profile') }}" style="color: #BBBBBB;" class="hover:text-opacity-80 transition-colors">
                            My Profile
                        </a>
                        <a href="{{ route('dashboard') }}" style="color: #BBBBBB;" class="hover:text-opacity-80 transition-colors">
                            Dashboard
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" style="color: #BBBBBB;" class="hover:text-opacity-80 transition-colors">
                                Logout
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" style="color: #BBBBBB;" class="hover:text-opacity-80 transition-colors">
                            Login
                        </a>
                        <a href="{{ route('register') }}" style="background-color: #01CADA; color: #0D0D0D;" class="px-4 py-2 rounded-lg hover:opacity-90 transition-opacity font-semibold">
                            Register
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Breadcrumb -->
            <div class="mb-6">
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="{{ route('home') }}" style="color: #01CADA;" class="hover:opacity-80">
                                Home
                            </a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <span style="color: #6C6C6C;">/</span>
                                <a href="{{ route('racks.show', $rack) }}" class="ml-1 md:ml-2" style="color: #01CADA;" class="hover:opacity-80">
                                    {{ $rack->title }}
                                </a>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <span style="color: #6C6C6C;">/</span>
                                <span class="ml-1 md:ml-2 text-sm font-medium" style="color: #0D0D0D;">Edit</span>
                            </div>
                        </li>
                    </ol>
                </nav>
            </div>

            <div class="rounded-lg p-8 mx-4" style="background-color: #4a4a4a; border: 1px solid #6C6C6C;">
                <h1 class="text-3xl font-bold mb-8" style="color: #BBBBBB;">Edit Rack</h1>

                @if ($errors->any())
                    <div class="mb-6 p-4 rounded-lg" style="background-color: #F87680; color: #0D0D0D;">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('racks.update', $rack) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <!-- Title -->
                    <div class="mb-6">
                        <label for="title" class="block text-sm font-medium mb-2" style="color: #BBBBBB;">
                            Title <span style="color: #F87680;">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            value="{{ old('title', $rack->title) }}"
                            required
                            maxlength="255"
                            class="w-full px-4 py-2 rounded-lg focus:ring-2 focus:outline-none"
                            style="background-color: #0D0D0D; border: 1px solid #6C6C6C; color: #BBBBBB; focus:ring-color: #01CADA;"
                        >
                    </div>

                    <!-- Description -->
                    <div class="mb-6">
                        <label for="description" class="block text-sm font-medium mb-2" style="color: #BBBBBB;">
                            Description <span style="color: #F87680;">*</span>
                        </label>
                        <textarea 
                            id="description" 
                            name="description" 
                            rows="4"
                            required
                            maxlength="1000"
                            class="w-full px-4 py-2 rounded-lg focus:ring-2 focus:outline-none"
                            style="background-color: #0D0D0D; border: 1px solid #6C6C6C; color: #BBBBBB; focus:ring-color: #01CADA;"
                        >{{ old('description', $rack->description) }}</textarea>
                        <p class="text-xs mt-1" style="color: #6C6C6C;">
                            Maximum 1000 characters
                        </p>
                    </div>

                    <!-- Category -->
                    <div class="mb-6">
                        <label for="category" class="block text-sm font-medium mb-2" style="color: #BBBBBB;">
                            Category
                        </label>
                        <select 
                            id="category" 
                            name="category"
                            class="w-full px-4 py-2 rounded-lg focus:ring-2 focus:outline-none"
                            style="background-color: #0D0D0D; border: 1px solid #6C6C6C; color: #BBBBBB; focus:ring-color: #01CADA;"
                        >
                            <option value="">Select a category...</option>
                            @foreach($categories as $category)
                                <option value="{{ $category }}" {{ old('category', $rack->category) == $category ? 'selected' : '' }}>
                                    {{ $category }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Tags -->
                    <div class="mb-6">
                        <label for="tags" class="block text-sm font-medium mb-2" style="color: #BBBBBB;">
                            Tags
                        </label>
                        <input 
                            type="text" 
                            id="tags" 
                            name="tags" 
                            value="{{ old('tags', $rack->tags->pluck('name')->implode(', ')) }}"
                            placeholder="ambient, chill, bass, etc. (comma separated)"
                            maxlength="500"
                            class="w-full px-4 py-2 rounded-lg focus:ring-2 focus:outline-none"
                            style="background-color: #0D0D0D; border: 1px solid #6C6C6C; color: #BBBBBB; focus:ring-color: #01CADA;"
                        >
                        <p class="text-xs mt-1" style="color: #6C6C6C;">
                            Separate tags with commas
                        </p>
                    </div>

                    <!-- How-to Guide -->
                    <div class="mb-6">
                        <label for="how_to_article" class="block text-sm font-medium mb-2" style="color: #BBBBBB;">
                            How-to Guide
                        </label>
                        <div class="bg-gray-800 rounded-lg border border-gray-600">
                            <x-markdown-editor 
                                name="how_to_article" 
                                :value="old('how_to_article', $rack->how_to_article ?? '')"
                                placeholder="Explain how to use this rack, what it does, and any special techniques... Use Markdown for formatting!"
                                :rows="12"
                                :maxlength="100000"
                                :showToolbar="true"
                                :showPreview="true"
                                :showImageUpload="false"
                                class="bg-gray-800 text-gray-200"
                            />
                        </div>
                        <p class="text-xs mt-1" style="color: #6C6C6C;">
                            Maximum 100,000 characters. Markdown formatting supported. This will help other producers understand how to use your rack effectively.
                        </p>
                    </div>

                    <!-- Chain Annotations -->
                    @if($rack->chains && count($rack->chains) > 0)
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4" style="color: #BBBBBB;">
                                Chain Notes & Names
                                <span class="text-sm font-normal" style="color: #6C6C6C;">(Help others understand your rack)</span>
                            </h3>
                            
                            @foreach($rack->chains as $chainIndex => $chain)
                                <div class="mb-4 p-4 rounded-lg" style="background-color: #0D0D0D; border: 1px solid #6C6C6C;">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- Custom Chain Name -->
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: #BBBBBB;">
                                                Chain {{ $chainIndex + 1 }} - Custom Name
                                            </label>
                                            <input 
                                                type="text" 
                                                name="chain_annotations[{{ $chainIndex }}][custom_name]"
                                                value="{{ old('chain_annotations.' . $chainIndex . '.custom_name', $rack->chain_annotations[$chainIndex]['custom_name'] ?? '') }}"
                                                placeholder="e.g., Bass Processing, Vocals, Lead Synth..."
                                                maxlength="100"
                                                class="w-full px-3 py-2 rounded focus:ring-2 focus:outline-none text-sm"
                                                style="background-color: #4a4a4a; border: 1px solid #6C6C6C; color: #BBBBBB; focus:ring-color: #01CADA;"
                                            >
                                            <p class="text-xs mt-1" style="color: #6C6C6C;">
                                                Original: {{ $chain['name'] ?? 'Chain ' . ($chainIndex + 1) }}
                                            </p>
                                        </div>

                                        <!-- Chain Note -->
                                        <div>
                                            <label class="block text-sm font-medium mb-2" style="color: #BBBBBB;">
                                                Chain {{ $chainIndex + 1 }} - Description/Note
                                            </label>
                                            <textarea 
                                                name="chain_annotations[{{ $chainIndex }}][note]"
                                                rows="3"
                                                placeholder="Explain what this chain does and why..."
                                                maxlength="500"
                                                class="w-full px-3 py-2 rounded focus:ring-2 focus:outline-none text-sm"
                                                style="background-color: #4a4a4a; border: 1px solid #6C6C6C; color: #BBBBBB; focus:ring-color: #01CADA;"
                                            >{{ old('chain_annotations.' . $chainIndex . '.note', $rack->chain_annotations[$chainIndex]['note'] ?? '') }}</textarea>
                                        </div>
                                    </div>
                                    
                                    <!-- Chain devices preview -->
                                    <div class="mt-3 pt-3 border-t" style="border-color: #6C6C6C;">
                                        <p class="text-xs" style="color: #6C6C6C;">
                                            <strong>Devices:</strong> 
                                            @if(isset($chain['devices']) && count($chain['devices']) > 0)
                                                {{ collect($chain['devices'])->pluck('name')->implode(', ') }}
                                            @else
                                                No devices
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                            
                            <div class="p-3 rounded-lg" style="background-color: rgba(1, 218, 218, 0.1);">
                                <p class="text-sm" style="color: #01CADA;">
                                    💡 <strong>Tip:</strong> Help other producers learn from your workflow! 
                                    Explain why you set up each chain this way, what it does, and any special techniques you used.
                                </p>
                            </div>
                        </div>
                    @endif

                    <!-- Technical Info (Read-only) -->
                    <div class="mb-6 p-4 rounded-lg" style="background-color: #0D0D0D;">
                        <h3 class="font-semibold mb-3" style="color: #BBBBBB;">Technical Information (Read-only)</h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span style="color: #6C6C6C;">Rack Type:</span>
                                <span style="color: #BBBBBB;" class="ml-2">
                                    {{ $rack->rack_type === 'AudioEffectGroupDevice' ? 'Audio Effect Rack' : 
                                       ($rack->rack_type === 'InstrumentGroupDevice' ? 'Instrument Rack' : 'MIDI Effect Rack') }}
                                </span>
                            </div>
                            <div>
                                <span style="color: #6C6C6C;">Ableton Edition:</span>
                                <span style="color: #BBBBBB;" class="ml-2">{{ ucfirst($rack->ableton_edition ?? 'Unknown') }}</span>
                            </div>
                            <div>
                                <span style="color: #6C6C6C;">Devices:</span>
                                <span style="color: #BBBBBB;" class="ml-2">{{ $rack->device_count }}</span>
                            </div>
                            <div>
                                <span style="color: #6C6C6C;">Chains:</span>
                                <span style="color: #BBBBBB;" class="ml-2">{{ $rack->chain_count }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex gap-4">
                        <button 
                            type="submit"
                            class="px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition-opacity"
                            style="background-color: #01DA48; color: #0D0D0D;"
                        >
                            Save Changes
                        </button>
                        <a 
                            href="{{ route('racks.show', $rack) }}"
                            class="px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition-opacity"
                            style="background-color: #6C6C6C; color: #BBBBBB;"
                        >
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>