<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ darkMode: $persist(false) }" x-bind:class="darkMode ? 'dark' : ''">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - Admin Dashboard</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        
        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        
        <!-- Alpine.js persist plugin -->
        <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/persist@3.x.x/dist/cdn.min.js"></script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <style>
            /* Custom scrollbar styling */
            ::-webkit-scrollbar {
                width: 6px;
                height: 6px;
            }
            
            ::-webkit-scrollbar-track {
                background: transparent;
            }
            
            ::-webkit-scrollbar-thumb {
                background: rgba(156, 163, 175, 0.5);
                border-radius: 3px;
            }
            
            ::-webkit-scrollbar-thumb:hover {
                background: rgba(156, 163, 175, 0.8);
            }
            
            .dark ::-webkit-scrollbar-thumb {
                background: rgba(75, 85, 99, 0.5);
            }
            
            .dark ::-webkit-scrollbar-thumb:hover {
                background: rgba(75, 85, 99, 0.8);
            }

            /* Loading skeleton animation */
            @keyframes shimmer {
                0% { background-position: -468px 0; }
                100% { background-position: 468px 0; }
            }
            
            .skeleton {
                animation: shimmer 1.2s ease-in-out infinite;
                background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 37%, #f0f0f0 63%);
                background-size: 400% 100%;
            }
            
            .dark .skeleton {
                background: linear-gradient(90deg, #374151 25%, #4b5563 37%, #374151 63%);
                background-size: 400% 100%;
            }
            
            /* Chart container styling */
            .chart-container {
                position: relative;
                height: 300px;
                width: 100%;
            }
            
            .chart-container canvas {
                max-height: 100% !important;
            }
        </style>
    </head>
    <body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 transition-colors duration-200">
        <div class="min-h-screen flex">
            <!-- Sidebar -->
            <div class="hidden lg:flex lg:w-64 lg:flex-col lg:fixed lg:inset-y-0">
                <div class="flex-1 flex flex-col min-h-0 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700">
                    <!-- Sidebar Header -->
                    <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
                        <div class="flex items-center flex-shrink-0 px-4 mb-8">
                            <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                            <span class="ml-2 text-xl font-semibold text-gray-900 dark:text-white">Admin</span>
                        </div>
                        
                        <!-- Navigation -->
                        <nav class="mt-5 flex-1 px-2 space-y-1">
                            <!-- Dashboard -->
                            <a href="{{ route('admin.dashboard') }}" 
                               class="admin-nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                                <svg class="mr-3 flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2v0" />
                                </svg>
                                Overview Dashboard
                            </a>
                            
                            <!-- Analytics -->
                            <a href="{{ route('admin.analytics.dashboard') }}" 
                               class="admin-nav-item {{ request()->routeIs('admin.analytics.*') ? 'active' : '' }}">
                                <svg class="mr-3 flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 00-2-2m0 0V5a2 2 0 012-2h2a2 2 0 00-2-2m-2 2h2" />
                                </svg>
                                Advanced Analytics
                            </a>
                            
                            <!-- System Monitoring -->
                            <a href="{{ route('admin.monitoring.dashboard') }}" 
                               class="admin-nav-item {{ request()->routeIs('admin.monitoring.*') ? 'active' : '' }}">
                                <svg class="mr-3 flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                System Monitor
                            </a>
                            
                            <!-- Content Management Section -->
                            <div class="mt-8">
                                <h3 class="px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Content
                                </h3>
                                <div class="mt-1 space-y-1">
                                    <a href="{{ route('admin.blog.index') }}" 
                                       class="admin-nav-item {{ request()->routeIs('admin.blog.*') ? 'active' : '' }}">
                                        <svg class="mr-3 flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9.5a2 2 0 00-2-2h-2m-4 3H9" />
                                        </svg>
                                        Blog Management
                                    </a>
                                    
                                    <a href="{{ route('admin.newsletter.index') }}" 
                                       class="admin-nav-item {{ request()->routeIs('admin.newsletter.*') ? 'active' : '' }}">
                                        <svg class="mr-3 flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        Newsletter
                                    </a>
                                </div>
                            </div>
                            
                            <!-- System Management Section -->
                            <div class="mt-8">
                                <h3 class="px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Management
                                </h3>
                                <div class="mt-1 space-y-1">
                                    <a href="{{ route('admin.issues.index') }}" 
                                       class="admin-nav-item {{ request()->routeIs('admin.issues.*') ? 'active' : '' }}">
                                        <svg class="mr-3 flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                        </svg>
                                        Issue Tracker
                                        @php
                                            $pendingIssues = \App\Models\Issue::whereIn('status', ['pending', 'in_review'])->count();
                                        @endphp
                                        @if($pendingIssues > 0)
                                            <span class="ml-auto inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                {{ $pendingIssues }}
                                            </span>
                                        @endif
                                    </a>
                                    
                                    <a href="{{ route('admin.feature-flags.index') }}" 
                                       class="admin-nav-item {{ request()->routeIs('admin.feature-flags.*') ? 'active' : '' }}">
                                        <svg class="mr-3 flex-shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                                        </svg>
                                        Feature Flags
                                    </a>
                                </div>
                            </div>
                        </nav>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="flex-shrink-0 flex bg-gray-50 dark:bg-gray-900 p-4">
                        <div class="flex items-center w-full group">
                            <div class="flex-shrink-0">
                                <img class="h-8 w-8 rounded-full" src="{{ auth()->user()->profile_photo_url }}" alt="{{ auth()->user()->name }}">
                            </div>
                            <div class="ml-3 flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate">
                                    {{ auth()->user()->name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Administrator</p>
                            </div>
                            <!-- Dark mode toggle -->
                            <button @click="darkMode = !darkMode" class="ml-2 p-1.5 rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                                <svg x-show="!darkMode" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                                <svg x-show="darkMode" class="h-4 w-4 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile menu -->
            <div x-data="{ open: false }" class="lg:hidden">
                <!-- Off-canvas menu for mobile -->
                <div x-show="open" class="fixed inset-0 z-40 flex" x-cloak>
                    <div x-show="open" 
                         x-transition:enter="transition-opacity ease-linear duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition-opacity ease-linear duration-300"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 bg-gray-600 bg-opacity-75" 
                         @click="open = false">
                    </div>
                    
                    <div x-show="open"
                         x-transition:enter="transition ease-in-out duration-300 transform"
                         x-transition:enter-start="-translate-x-full"
                         x-transition:enter-end="translate-x-0"
                         x-transition:leave="transition ease-in-out duration-300 transform"
                         x-transition:leave-start="translate-x-0"
                         x-transition:leave-end="-translate-x-full"
                         class="relative flex-1 flex flex-col max-w-xs w-full bg-white dark:bg-gray-800 pt-5 pb-4">
                        <div class="absolute top-0 right-0 -mr-12 pt-2">
                            <button @click="open = false" class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white">
                                <span class="sr-only">Close sidebar</span>
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Mobile Navigation (duplicate of desktop) -->
                        <div class="flex-1 h-0 pt-5 pb-4 overflow-y-auto">
                            <div class="flex items-center flex-shrink-0 px-4">
                                <x-application-logo class="block h-8 w-auto fill-current text-gray-800 dark:text-gray-200" />
                                <span class="ml-2 text-lg font-semibold text-gray-900 dark:text-white">Admin</span>
                            </div>
                            <!-- Copy navigation from desktop version -->
                        </div>
                    </div>
                </div>

                <!-- Top bar for mobile -->
                <div class="lg:hidden">
                    <div class="flex items-center justify-between bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-2">
                        <button @click="open = true" class="text-gray-500 dark:text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <span class="sr-only">Open sidebar</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                            </svg>
                        </button>
                        <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Admin Dashboard</h1>
                        <button @click="darkMode = !darkMode" class="p-1.5 rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                            <svg x-show="!darkMode" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                            <svg x-show="darkMode" class="h-4 w-4 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <div class="lg:pl-64 flex flex-col flex-1">
                <main class="flex-1">
                    <!-- Page header -->
                    @hasSection('header')
                        <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                            <div class="px-4 py-6 sm:px-6 lg:px-8">
                                @yield('header')
                            </div>
                        </div>
                    @endif

                    <!-- Page content -->
                    <div class="py-6">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            @if (session('status'))
                                <div class="mb-6 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-200 px-4 py-3 rounded relative">
                                    {{ session('status') }}
                                </div>
                            @endif

                            @if (session('error'))
                                <div class="mb-6 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200 px-4 py-3 rounded relative">
                                    {{ session('error') }}
                                </div>
                            @endif

                            {{ $slot }}
                        </div>
                    </div>
                </main>
                
                {{-- Footer --}}
                <x-footer />
            </div>
        </div>

        <!-- Page specific scripts -->
        @stack('scripts')

        <!-- Global admin JavaScript -->
        <script>
            // Global admin utilities
            window.AdminUtils = {
                // Flash message system
                showFlashMessage(message, type = 'success') {
                    // Implementation for showing flash messages
                    console.log(`${type}: ${message}`);
                },
                
                // CSRF token helper
                getCsrfToken() {
                    return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                },
                
                // API request helper
                async apiRequest(url, options = {}) {
                    const defaultOptions = {
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.getCsrfToken(),
                            'Accept': 'application/json',
                        },
                    };
                    
                    return fetch(url, { ...defaultOptions, ...options });
                },
                
                // Number formatting
                formatNumber(num) {
                    return new Intl.NumberFormat().format(num);
                },
                
                // Date formatting
                formatDate(date) {
                    return new Date(date).toLocaleDateString();
                }
            };

            // Auto-refresh functionality for real-time data
            window.AdminRealTime = {
                intervals: new Map(),
                
                start(key, callback, intervalMs = 30000) {
                    this.stop(key);
                    const interval = setInterval(callback, intervalMs);
                    this.intervals.set(key, interval);
                },
                
                stop(key) {
                    if (this.intervals.has(key)) {
                        clearInterval(this.intervals.get(key));
                        this.intervals.delete(key);
                    }
                },
                
                stopAll() {
                    this.intervals.forEach((interval, key) => {
                        this.stop(key);
                    });
                }
            };

            // Cleanup on page unload
            window.addEventListener('beforeunload', () => {
                if (window.AdminRealTime) {
                    window.AdminRealTime.stopAll();
                }
            });
        </script>

        <style>
            /* Admin navigation styles */
            .admin-nav-item {
                @apply text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white group flex items-center px-2 py-2 text-sm font-medium rounded-md transition-all duration-200;
            }
            
            .admin-nav-item.active {
                @apply bg-blue-50 dark:bg-blue-900 border-r-2 border-blue-500 text-blue-700 dark:text-blue-200;
            }
            
            .admin-nav-item svg {
                @apply text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-400;
            }
            
            .admin-nav-item.active svg {
                @apply text-blue-500 dark:text-blue-300;
            }
            
            /* Card styling */
            .admin-card {
                @apply bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700;
            }
            
            .admin-card-header {
                @apply px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900;
            }
            
            .admin-card-body {
                @apply px-6 py-4;
            }
            
            /* Button styles */
            .admin-btn-primary {
                @apply inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2;
            }
            
            .admin-btn-secondary {
                @apply inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2;
            }
        </style>
    </body>
</html>