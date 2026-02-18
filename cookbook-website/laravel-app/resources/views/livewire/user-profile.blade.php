<div>
    <!-- Profile Header -->
    <div class="card p-6 mb-8">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <!-- User Info -->
        <div class="flex items-center gap-4">
            <div class="w-20 h-20 rounded-full flex items-center justify-center text-3xl font-bold text-white" style="background-color: #01CADA;">
                {{ strtoupper(substr($user->name, 0, 2)) }}
            </div>
            <div class="flex-1">
                <h1 class="text-3xl font-bold text-black" itemprop="name">{{ $user->name }}</h1>
                <p class="text-lg text-gray-600">
                    @if($isOwnProfile)
                        Your Profile
                    @else
                        Community Member
                    @endif
                </p>
                <p class="text-sm text-gray-500">
                    Member since {{ $user->created_at->format('F Y') }}
                    @if($user->location)
                        • {{ $user->location }}
                    @endif
                </p>
                
                @if($user->bio)
                    <div class="mt-2 text-sm text-gray-700" itemprop="description">{{ $user->bio }}</div>
                @endif
                
                {{-- Hidden SEO content --}}
                <div class="sr-only">
                    <span itemprop="jobTitle">Music Producer</span>
                    <span itemprop="knowsAbout">Ableton Live, Music Production</span>
                    <div itemprop="memberOf" itemscope itemtype="https://schema.org/Organization">
                        <span itemprop="name">Ableton Cookbook Community</span>
                    </div>
                </div>
                
                <!-- Social Media Links -->
                @php
                    $socialLinks = [
                        'website' => ['icon' => '🌐', 'label' => 'Website'],
                        'soundcloud_url' => ['icon' => '🎵', 'label' => 'SoundCloud'],
                        'bandcamp_url' => ['icon' => '🎶', 'label' => 'Bandcamp'],
                        'spotify_url' => ['icon' => '🟢', 'label' => 'Spotify'],
                        'youtube_url' => ['icon' => '▶️', 'label' => 'YouTube'],
                        'instagram_url' => ['icon' => '📷', 'label' => 'Instagram'],
                        'twitter_url' => ['icon' => '🐦', 'label' => 'Twitter'],
                    ];
                    $userSocialLinks = array_filter($socialLinks, fn($key) => !empty($user->{$key}), ARRAY_FILTER_USE_KEY);
                @endphp
                
                @if(is_countable($userSocialLinks) && count($userSocialLinks) > 0)
                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach($userSocialLinks as $field => $social)
                            <a href="{{ $user->{$field} }}" target="_blank" 
                               class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs hover:opacity-80 transition-opacity text-white"
                               style="background-color: #01CADA;"
                               title="{{ $social['label'] }}">
                                <span>{{ $social['icon'] }}</span>
                                {{ $social['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Profile Stats -->
        @if($isOwnProfile)
            <!-- Full stats for owner -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-6 text-center">
                <div>
                    <div class="text-2xl font-bold" style="color: #01CADA;">{{ $stats['total_uploads'] }}</div>
                    <div class="text-sm text-gray-500">Uploads</div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: #01CADA;">{{ number_format($stats['total_downloads']) }}</div>
                    <div class="text-sm text-gray-500">Downloads</div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: #01CADA;">{{ number_format($stats['total_views']) }}</div>
                    <div class="text-sm text-gray-500">Views</div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: #01CADA;">{{ $stats['total_favorites'] }}</div>
                    <div class="text-sm text-gray-500">Favorited</div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: #ffdf00;">{{ number_format($stats['average_rating'], 1) }}</div>
                    <div class="text-sm text-gray-500">Avg Rating</div>
                </div>
            </div>
        @else
            <!-- Public stats for others -->
            <div class="grid grid-cols-1 gap-6 text-center">
                <div>
                    <div class="text-2xl font-bold" style="color: #01CADA;">{{ $stats['total_uploads'] }}</div>
                    <div class="text-sm text-gray-500">Public Uploads</div>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Profile Actions & Tabs -->
<div class="mb-8">
    @if($isOwnProfile)
        <div class="mb-4">
            <a href="/user/profile" 
               class="btn-primary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit Profile & Settings
            </a>
        </div>
    @endif
    
    <div class="card p-1">
        <div class="flex gap-1">
            <button 
                wire:click="setActiveTab('uploads')"
                class="flex-1 px-4 py-2 rounded text-sm font-medium transition-all {{ $activeTab === 'uploads' ? 'btn-primary' : 'text-gray-600 hover:text-gray-800' }}"
            >
                📤 Uploaded Racks ({{ $stats['total_uploads'] }})
            </button>
            @if($isOwnProfile)
                <button 
                    wire:click="setActiveTab('favorites')"
                    class="flex-1 px-4 py-2 rounded text-sm font-medium transition-all {{ $activeTab === 'favorites' ? 'btn-primary' : 'text-gray-600 hover:text-gray-800' }}"
                >
                    💖 My Favorites
                </button>
            @endif
        </div>
    </div>
</div>

<!-- Tab Content -->
@if($activeTab === 'uploads')
    <!-- Uploaded Racks -->
    <div class="mb-8">
        <h2 class="text-xl font-bold mb-4 text-black">
            {{ $isOwnProfile ? 'Your Uploaded Racks' : $user->name . '\'s Uploaded Racks' }}
            @if($stats['total_uploads'] > 0)
                <span class="text-sm font-normal text-gray-600">({{ $stats['total_uploads'] }} {{ Str::plural('rack', $stats['total_uploads']) }})</span>
            @endif
        </h2>
        
        {{-- Hidden SEO content --}}
        <div class="sr-only">
            <h3>Professional Music Production Content</h3>
            <p>{{ $user->name }} has shared {{ $stats['total_uploads'] }} high-quality Ableton Live racks with the music production community.</p>
        </div>
        
        @if($racks->count() > 0)
            <!-- Racks Grid - SAME STYLING AS BROWSE PAGE -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8 mb-12">
                @foreach($racks as $rack)
                    <div 
                        onclick="window.location.href='{{ route('racks.show', $rack) }}'"
                        class="card hover:shadow-lg transition-all cursor-pointer h-full flex flex-col group"
                    >
                        <!-- Rack Header -->
                        <div class="p-4 border-b-2 border-black">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-bold truncate text-black group-hover:text-vibrant-purple transition-colors">
                                        {{ $rack->title }}
                                    </h3>
                                    <p class="text-sm mt-1 text-gray-600">
                                        by 
                                        <a 
                                            href="{{ route('users.show', $rack->user) }}" 
                                            onclick="event.stopPropagation();"
                                            class="link"
                                        >
                                            {{ $rack->user->name }}
                                        </a>
                                    </p>
                                </div>
                                
                                <!-- Compact Actions -->
                                <div class="flex items-center gap-1">
                                    @auth
                                        <button 
                                            wire:click="toggleFavorite({{ $rack->id }})"
                                            onclick="event.stopPropagation();"
                                            class="p-1 hover:scale-110 transition-transform"
                                            title="{{ $rack->is_favorited_by_user ? 'Remove from favorites' : 'Add to favorites' }}"
                                        >
                                            <svg class="w-5 h-5 favorite-btn {{ $rack->is_favorited_by_user ? 'active' : '' }}" fill="{{ $rack->is_favorited_by_user ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                            </svg>
                                        </button>
                                    @endauth
                                    
                                    <!-- Rating -->
                                    <div class="flex items-center gap-1">
                                        <svg class="w-4 h-4 star-btn {{ $rack->average_rating > 0 ? 'active' : '' }}" fill="{{ $rack->average_rating > 0 ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                        </svg>
                                        <span class="text-sm font-medium text-black">{{ number_format($rack->average_rating, 1) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rack Content -->
                        <div class="p-4 flex-1 flex flex-col">
                            <!-- Description -->
                            <p class="text-sm mb-4 line-clamp-2 flex-1 text-gray-700 leading-relaxed">{{ $rack->description }}</p>
                            
                            <!-- Bottom Info -->
                            <div class="mt-auto">
                                <!-- Badges Row -->
                                <div class="flex flex-wrap gap-2">
                                    <!-- Ableton Edition -->
                                    @if($rack->ableton_edition)
                                        <span class="ableton-{{ $rack->ableton_edition }}-svg">
                                            @if($rack->ableton_edition === 'intro')
                                                <x-icons.ableton-intro />
                                            @elseif($rack->ableton_edition === 'standard')
                                                <x-icons.ableton-standard />
                                            @elseif($rack->ableton_edition === 'suite')
                                                <x-icons.ableton-suite />
                                            @endif
                                            {{ ucfirst($rack->ableton_edition) }}
                                        </span>
                                    @endif
                                    
                                    <!-- Category -->
                                    @if($rack->category)
                                        <span class="badge-category">
                                            {{ $rack->category }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12">
                <div class="text-6xl mb-4 text-gray-400">🎛️</div>
                <h3 class="text-lg font-medium mb-2 text-black">
                    {{ $isOwnProfile ? 'No uploaded racks yet' : 'No public racks found' }}
                </h3>
                <p class="text-gray-600">
                    {{ $isOwnProfile ? 'Upload your first rack to get started!' : 'This user hasn\'t uploaded any public racks yet.' }}
                </p>
            </div>
        @endif
    </div>
@elseif($activeTab === 'favorites' && $isOwnProfile)
    <!-- Favorite Racks -->
    <div class="mb-8">
        <h2 class="text-xl font-bold mb-4 text-black">Your Favorite Racks</h2>
        
        @if($racks->count() > 0)
            <!-- Racks Grid - SAME STYLING AS BROWSE PAGE -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8 mb-12">
                @foreach($racks as $rack)
                    <div 
                        onclick="window.location.href='{{ route('racks.show', $rack) }}'"
                        class="card hover:shadow-lg transition-all cursor-pointer h-full flex flex-col group"
                    >
                        <!-- Rack Header -->
                        <div class="p-4 border-b-2 border-black">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-bold truncate text-black group-hover:text-vibrant-purple transition-colors">
                                        {{ $rack->title }}
                                    </h3>
                                    <p class="text-sm mt-1 text-gray-600">
                                        by 
                                        <a 
                                            href="{{ route('users.show', $rack->user) }}" 
                                            onclick="event.stopPropagation();"
                                            class="link"
                                        >
                                            {{ $rack->user->name }}
                                        </a>
                                    </p>
                                </div>
                                
                                <!-- Compact Actions -->
                                <div class="flex items-center gap-1">
                                    <!-- Always show filled heart for favorites -->
                                    <button 
                                        wire:click="toggleFavorite({{ $rack->id }})"
                                        onclick="event.stopPropagation();"
                                        class="p-1 hover:scale-110 transition-transform"
                                        title="Remove from favorites"
                                    >
                                        <svg class="w-5 h-5 favorite-btn active" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                        </svg>
                                    </button>
                                    
                                    <!-- Rating -->
                                    <div class="flex items-center gap-1">
                                        <svg class="w-4 h-4 star-btn {{ $rack->average_rating > 0 ? 'active' : '' }}" fill="{{ $rack->average_rating > 0 ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                        </svg>
                                        <span class="text-sm font-medium text-black">{{ number_format($rack->average_rating, 1) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rack Content -->
                        <div class="p-4 flex-1 flex flex-col">
                            <!-- Description -->
                            <p class="text-sm mb-4 line-clamp-2 flex-1 text-gray-700 leading-relaxed">{{ $rack->description }}</p>
                            
                            <!-- Bottom Info -->
                            <div class="mt-auto">
                                <!-- Badges Row -->
                                <div class="flex flex-wrap gap-2">
                                    <!-- Ableton Edition -->
                                    @if($rack->ableton_edition)
                                        <span class="ableton-{{ $rack->ableton_edition }}-svg">
                                            @if($rack->ableton_edition === 'intro')
                                                <x-icons.ableton-intro />
                                            @elseif($rack->ableton_edition === 'standard')
                                                <x-icons.ableton-standard />
                                            @elseif($rack->ableton_edition === 'suite')
                                                <x-icons.ableton-suite />
                                            @endif
                                            {{ ucfirst($rack->ableton_edition) }}
                                        </span>
                                    @endif
                                    
                                    <!-- Category -->
                                    @if($rack->category)
                                        <span class="badge-category">
                                            {{ $rack->category }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12">
                <div class="text-6xl mb-4" style="color: #F87680;">💖</div>
                <h3 class="text-lg font-medium mb-2 text-black">No favorites yet</h3>
                <p class="text-gray-600">Start exploring and heart racks you love!</p>
                <a href="{{ route('home') }}" class="btn-primary inline-block mt-4">
                    Browse Racks
                </a>
            </div>
        @endif
    </div>
@endif

<!-- Pagination -->
@if($racks->hasPages())
    <div class="flex justify-center">
        {{ $racks->links() }}
    </div>
@endif

<!-- Flash Messages -->
@if(session()->has('success'))
    <div class="fixed bottom-4 right-4 rounded-lg p-4 shadow-lg text-white" style="background-color: #01DA48;">
        {{ session('success') }}
    </div>
@endif

@if(session()->has('error'))
    <div class="fixed bottom-4 right-4 rounded-lg p-4 shadow-lg text-white" style="background-color: #F87680;">
        {{ session('error') }}
    </div>
@endif

{{-- Internal Linking for SEO --}}
<x-internal-links :user="$user" />

{{-- Additional SEO content --}}
<div class="sr-only">
    <h2>About {{ $user->name }}</h2>
    <p>{{ $user->name }} is a talented music producer and member of the Ableton Cookbook community since {{ $user->created_at->format('F Y') }}. They have contributed {{ $stats['total_uploads'] }} Ableton Live racks to help fellow producers in their music creation journey.</p>
    
    @if($stats['total_uploads'] > 0)
        <h3>Music Production Expertise</h3>
        <p>{{ $user->name }}'s racks have gained significant traction in the community with {{ number_format($stats['total_downloads']) }} total downloads and an average rating of {{ number_format($stats['average_rating'], 1) }} stars.</p>
    @endif
    
    <h3>Join the Community</h3>
    <p>Discover more Ableton Live racks, connect with music producers, and share your own creations on Ableton Cookbook. Whether you're looking for instrument racks, audio effect racks, or MIDI racks, our community has everything you need for your music production workflow.</p>
</div>
</div>
