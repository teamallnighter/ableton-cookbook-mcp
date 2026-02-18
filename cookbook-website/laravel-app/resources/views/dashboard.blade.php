<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Welcome Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-ableton-light">
                Welcome back, {{ Auth::user()->name }}
            </h1>
            <p class="mt-2 text-ableton-light/70">
                Ready to create something amazing? Here's your music production dashboard.
            </p>
        </div>

        <!-- Stats Overview - Track-inspired layout -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Upload Stats -->
            <div class="card">
                <div class="card-body flex items-center">
                    <div class="w-12 h-12 bg-ableton-accent rounded-lg flex items-center justify-center mr-4">
                        <svg class="w-6 h-6 text-ableton-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-ableton-light">
                            {{ Auth::user()->racks()->count() }}
                        </h3>
                        <p class="text-sm text-ableton-light/70">Racks Uploaded</p>
                    </div>
                </div>
            </div>

            <!-- Favorites -->
            <div class="card">
                <div class="card-body flex items-center">
                    <div class="w-12 h-12 bg-ableton-danger rounded-lg flex items-center justify-center mr-4">
                        <svg class="w-6 h-6 text-ableton-black" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-ableton-light">
                            {{ Auth::user()->favorites()->count() }}
                        </h3>
                        <p class="text-sm text-ableton-light/70">Favorites</p>
                    </div>
                </div>
            </div>

            <!-- Rating Average -->
            <div class="card">
                <div class="card-body flex items-center">
                    <div class="w-12 h-12 bg-ableton-warning rounded-lg flex items-center justify-center mr-4">
                        <svg class="w-6 h-6 text-ableton-black" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-ableton-light">
                            {{ number_format(Auth::user()->racks()->where('status', 'approved')->avg('average_rating') ?? 0, 1) }}
                        </h3>
                        <p class="text-sm text-ableton-light/70">Avg Rating</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions - Session View inspired -->
        <div class="card mb-8">
            <div class="card-header">
                <h2 class="text-xl font-semibold text-ableton-light">Quick Actions</h2>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="{{ route('racks.upload') }}" 
                       class="p-4 bg-ableton-black border border-ableton-light/20 rounded-lg hover:border-ableton-accent transition-colors group">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-ableton-accent rounded flex items-center justify-center mr-3 group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5 text-ableton-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-medium text-ableton-light">Upload New Rack</h3>
                                <p class="text-sm text-ableton-light/70">Share your creation</p>
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('home') }}" 
                       class="p-4 bg-ableton-black border border-ableton-light/20 rounded-lg hover:border-ableton-accent transition-colors group">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-ableton-accent rounded flex items-center justify-center mr-3 group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5 text-ableton-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-medium text-ableton-light">Browse Racks</h3>
                                <p class="text-sm text-ableton-light/70">Discover new sounds</p>
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('profile.show') }}" 
                       class="p-4 bg-ableton-black border border-ableton-light/20 rounded-lg hover:border-ableton-accent transition-colors group">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-ableton-accent rounded flex items-center justify-center mr-3 group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5 text-ableton-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-medium text-ableton-light">Manage Profile</h3>
                                <p class="text-sm text-ableton-light/70">Update your settings</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Your Recent Racks -->
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-ableton-light">Your Recent Racks</h2>
                    <a href="{{ route('home') }}" class="text-sm text-ableton-accent hover:opacity-90">View All</a>
                </div>
                <div class="card-body space-y-3">
                    @forelse(Auth::user()->racks()->latest()->take(3)->get() as $rack)
                        <div class="flex items-center justify-between p-3 bg-ableton-black rounded border border-ableton-light/10">
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium text-ableton-light truncate">{{ $rack->title }}</h4>
                                <p class="text-sm text-ableton-light/70">{{ $rack->created_at->diffForHumans() }}</p>
                            </div>
                            <div class="flex items-center space-x-2 text-sm text-ableton-light/70">
                                <span>{{ $rack->downloads_count ?? 0 }} downloads</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6">
                            <div class="text-4xl mb-2">🎵</div>
                            <p class="text-ableton-light/70">No racks uploaded yet</p>
                            <a href="{{ route('racks.upload') }}" class="text-ableton-accent hover:opacity-90 text-sm">Upload your first rack</a>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Community Highlights -->
            <div class="card">
                <div class="card-header">
                    <h2 class="text-xl font-semibold text-ableton-light">Community Highlights</h2>
                </div>
                <div class="card-body space-y-3">
                    @php
                        $popularRacks = \App\Models\Rack::withCount('ratings')
                            ->orderBy('ratings_count', 'desc')
                            ->take(3)
                            ->get();
                    @endphp
                    
                    @foreach($popularRacks as $rack)
                        <div class="flex items-center justify-between p-3 bg-ableton-black rounded border border-ableton-light/10">
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium text-ableton-light truncate">{{ $rack->title }}</h4>
                                <p class="text-sm text-ableton-light/70">by {{ $rack->user->name }}</p>
                            </div>
                            <div class="flex items-center space-x-1 text-sm">
                                <svg class="w-4 h-4 text-ableton-warning" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                                <span class="text-ableton-light">{{ number_format($rack->average_rating, 1) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
