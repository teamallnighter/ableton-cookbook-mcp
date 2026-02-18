<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="refresh" content="3;url={{ route('racks.edit.analysis', $rack) }}">

    <title>Analyzing Rack - {{ config('app.name', 'Ableton Cookbook') }}</title>

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
                
                <!-- Step 2: Annotate (Processing) -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-vibrant-yellow flex items-center justify-center">
                        <svg class="animate-spin h-5 w-5 text-black" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <span class="ml-2 text-black font-semibold">Analyzing</span>
                </div>
                
                <!-- Connector -->
                <div class="w-16 h-1 bg-gray-300"></div>
                
                <!-- Step 3: Details (Inactive) -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold">
                        3
                    </div>
                    <span class="ml-2 text-gray-600">Details</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        <div class="card card-body text-center">
            <div class="mb-8">
                <div class="w-24 h-24 rounded-full bg-vibrant-yellow/20 flex items-center justify-center mx-auto mb-6">
                    <svg class="animate-spin h-12 w-12 text-vibrant-yellow" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                
                <h1 class="text-3xl font-bold mb-4 text-black">Analyzing Your Rack</h1>
                <p class="text-gray-600 text-lg mb-2">Please wait while we examine your rack structure...</p>
                <p class="text-sm text-gray-500">File: {{ $rack->original_filename }}</p>
            </div>

            <!-- What We're Analyzing -->
            <div class="bg-gray-50 rounded-lg p-6 mb-6">
                <h2 class="text-xl font-bold text-black mb-4">What we're analyzing</h2>
                <div class="grid md:grid-cols-3 gap-4 text-sm">
                    <div class="flex items-center">
                        <div class="w-2 h-2 rounded-full bg-vibrant-green mr-2"></div>
                        <span>Rack type detection</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-2 h-2 rounded-full bg-vibrant-green mr-2"></div>
                        <span>Chain structure</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-2 h-2 rounded-full bg-vibrant-green mr-2"></div>
                        <span>Device inventory</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-2 h-2 rounded-full bg-vibrant-green mr-2"></div>
                        <span>Macro controls</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-2 h-2 rounded-full bg-vibrant-green mr-2"></div>
                        <span>Category suggestions</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-2 h-2 rounded-full bg-vibrant-green mr-2"></div>
                        <span>Version compatibility</span>
                    </div>
                </div>
            </div>

            <!-- Progress Tip -->
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <p class="text-blue-800 font-medium mb-1">💡 Pro Tip</p>
                <p class="text-blue-700 text-sm">This page will automatically refresh when analysis is complete, or you can manually refresh.</p>
            </div>
        </div>
    </div>
</body>
</html>
