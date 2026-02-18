<nav x-data="{ open: false }" class="bg-white border-b-2 border-black shadow-sm">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center space-x-3 hover:opacity-90 transition-opacity">
                        <x-application-logo class="w-8 h-8 text-black" />
                        <span class="text-black font-bold hidden sm:block">Ableton Cookbook</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-4 sm:ml-10 sm:flex">
                    <a href="{{ route('dashboard') }}" 
                       class="link {{ request()->routeIs('dashboard') ? 'font-bold' : '' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('home') }}" 
                       class="link {{ request()->routeIs('home') ? 'font-bold' : '' }}">
                        Browse Racks
                    </a>
                    <a href="{{ route('racks.upload') }}" 
                       class="link {{ request()->routeIs('racks.upload') ? 'font-bold' : '' }}">
                        Upload
                    </a>
                    <a href="{{ route('about') }}" 
                       class="link {{ request()->routeIs('about') ? 'font-bold' : '' }}">
                        About
                    </a>
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:space-x-4">
                @auth
                    <!-- Notifications (if implemented) -->
                    @if(false) <!-- Add when notification system is ready -->
                        <button class="relative p-2 text-ableton-light hover:bg-ableton-light/10 rounded-full transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zm-10-4h10l-5-5v5z"></path>
                            </svg>
                            <span class="absolute top-1 right-1 w-2 h-2 bg-ableton-accent rounded-full"></span>
                        </button>
                    @endif

                    <!-- User Menu -->
                    <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" 
                            class="flex items-center space-x-3 px-3 py-2 text-sm font-medium text-black hover:bg-gray-100 rounded transition-colors focus:outline-none focus:ring-2 focus:ring-black">
                        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                            <img class="w-8 h-8 rounded-full object-cover border-2 border-black" 
                                 src="{{ Auth::user()->profile_photo_url }}" 
                                 alt="{{ Auth::user()->name }}" />
                        @else
                            <div class="w-8 h-8 bg-vibrant-purple rounded-full flex items-center justify-center">
                                <span class="text-white font-bold text-sm">
                                    {{ substr(Auth::user()->name, 0, 1) }}
                                </span>
                            </div>
                        @endif
                        <span class="hidden md:block">{{ Auth::user()->name }}</span>
                        <svg class="w-4 h-4 transform transition-transform" :class="{ 'rotate-180': open }" 
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div x-show="open" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         @click.away="open = false"
                         class="absolute right-0 mt-2 w-48 bg-white border-2 border-black rounded-lg shadow-lg z-50">
                        
                        <!-- User Info Header -->
                        <div class="px-4 py-3 border-b-2 border-black">
                            <p class="text-sm font-bold text-black">{{ Auth::user()->name }}</p>
                            <p class="text-xs text-gray-600">{{ Auth::user()->email }}</p>
                        </div>

                        <!-- Menu Items -->
                        <div class="py-1">
                            <a href="{{ route('profile.show') }}" 
                               class="flex items-center px-4 py-2 text-sm text-black hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Profile
                            </a>
                            
                            <a href="#" class="flex items-center px-4 py-2 text-sm text-black hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                </svg>
                                Favorites
                            </a>
                        </div>

                        <div class="border-t-2 border-black">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" 
                                        class="flex items-center w-full px-4 py-2 text-sm text-black hover:bg-gray-100 transition-colors">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    Sign Out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @endauth

                @guest
                    <a href="{{ route('login') }}" class="link">Log in</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">Sign up</a>
                @endguest
            </div>

            <!-- Mobile Menu Button -->
            <div class="flex items-center sm:hidden">
                <button @click="open = !open" 
                        class="inline-flex items-center justify-center p-2 rounded-md text-black hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-black transition-colors">
                    <svg class="w-6 h-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': !open}" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': !open, 'inline-flex': open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation Menu -->
    <div :class="{'block': open, 'hidden': !open}" class="hidden sm:hidden bg-white border-t-2 border-black">
        <!-- Navigation Links -->
        <div class="px-2 pt-2 pb-3 space-y-1">
            <a href="{{ route('dashboard') }}" 
               class="block px-3 py-2 text-base font-medium rounded-md transition-colors {{ request()->routeIs('dashboard') ? 'bg-vibrant-purple text-white' : 'text-black hover:bg-gray-100' }}">
                Dashboard
            </a>
            <a href="{{ route('home') }}" 
               class="block px-3 py-2 text-base font-medium rounded-md transition-colors {{ request()->routeIs('home') ? 'bg-vibrant-purple text-white' : 'text-black hover:bg-gray-100' }}">
                Browse Racks
            </a>
            <a href="{{ route('racks.upload') }}" 
               class="block px-3 py-2 text-base font-medium rounded-md transition-colors {{ request()->routeIs('racks.upload') ? 'bg-vibrant-purple text-white' : 'text-black hover:bg-gray-100' }}">
                Upload
            </a>
            <a href="{{ route('about') }}" 
               class="block px-3 py-2 text-base font-medium rounded-md transition-colors {{ request()->routeIs('about') ? 'bg-vibrant-purple text-white' : 'text-black hover:bg-gray-100' }}">
                About
            </a>
        </div>

        <!-- User Section -->
        @auth
            <div class="pt-4 pb-1 border-t-2 border-black">
                <div class="flex items-center px-4 py-2">
                    @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                        <div class="flex-shrink-0">
                            <img class="w-10 h-10 rounded-full object-cover border-2 border-black" 
                                 src="{{ Auth::user()->profile_photo_url }}" 
                                 alt="{{ Auth::user()->name }}" />
                        </div>
                    @else
                        <div class="flex-shrink-0 w-10 h-10 bg-vibrant-purple rounded-full flex items-center justify-center">
                            <span class="text-white font-medium">
                                {{ substr(Auth::user()->name, 0, 1) }}
                            </span>
                        </div>
                    @endif

                    <div class="ml-3">
                        <div class="text-base font-medium text-black">{{ Auth::user()->name }}</div>
                        <div class="text-sm text-gray-600">{{ Auth::user()->email }}</div>
                    </div>
                </div>

                <div class="mt-3 px-2 space-y-1">
                    <a href="{{ route('profile.show') }}" 
                       class="flex items-center px-3 py-2 text-base font-medium text-black hover:bg-gray-100 rounded-md transition-colors {{ request()->routeIs('profile.show') ? 'bg-vibrant-purple text-white' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Profile
                    </a>
                    
                    <a href="#" class="flex items-center px-3 py-2 text-base font-medium text-black hover:bg-gray-100 rounded-md transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        Favorites
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" 
                                class="flex items-center w-full px-3 py-2 text-base font-medium text-black hover:bg-gray-100 rounded-md transition-colors">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            Sign Out
                        </button>
                    </form>
                </div>
            </div>
        @endauth

        @guest
            <div class="pt-4 pb-1 border-t-2 border-black">
                <div class="px-2 space-y-1">
                    <a href="{{ route('login') }}" 
                       class="block px-3 py-2 text-base font-medium text-black hover:bg-gray-100 rounded-md transition-colors">
                        Log in
                    </a>
                    <a href="{{ route('register') }}" 
                       class="block px-3 py-2 text-base font-medium text-white bg-vibrant-purple hover:bg-vibrant-purple/90 rounded-md transition-colors">
                        Sign up
                    </a>
                </div>
            </div>
        @endguest
    </div>
</nav>
