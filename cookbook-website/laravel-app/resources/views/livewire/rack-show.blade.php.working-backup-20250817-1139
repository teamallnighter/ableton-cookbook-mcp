<div class="max-w-6xl mx-auto px-6 py-8">
    <!-- Breadcrumb -->
    <div class="mb-8">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('dashboard') }}" class="text-ableton-accent hover:text-ableton-warning transition-colors">
                        Home
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <span class="text-ableton-light/60">/</span>
                        <span class="ml-3 text-sm font-medium text-ableton-light">{{ $rack->title }}</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <!-- Rack Header -->
    <div class="card card-body mb-12">
        <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-6">
            <!-- Main Info -->
            <div class="flex-1">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h1 class="text-4xl font-bold mb-4 text-black" itemprop="name">{{ $rack->title }}</h1>
                        <p class="text-lg text-gray-700">
                            by 
                            <a 
                                href="{{ route('users.show', $rack->user) }}" 
                                class="link"
                            >
                                {{ $rack->user->name }}
                            </a>
                        </p>
                    </div>
                    
                    <div class="flex items-center gap-3 ml-4">
                        <!-- Edition Badge -->
                        @if($rack->ableton_edition)
                            <span class="text-sm px-4 py-2 rounded-full font-medium {{ $rack->ableton_edition === 'suite' ? 'edition-suite' : ($rack->ableton_edition === 'standard' ? 'edition-standard' : 'edition-intro') }}">
                                Live {{ ucfirst($rack->ableton_edition) }}
                            </span>
                        @endif
                        
                        <!-- Rating Display -->
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-star-yellow" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            <span class="font-semibold text-black">{{ number_format($rack->average_rating, 1) }}</span>
                            <span class="text-sm text-gray-600">({{ $rack->ratings_count }} {{ Str::plural('rating', $rack->ratings_count) }})</span>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-8">
                    <div itemprop="description" class="text-lg leading-relaxed text-gray-800">{{ $rack->description }}</div>
                    
                    {{-- Keywords for SEO --}}
                    <div class="sr-only">
                        <span itemprop="keywords">{{ $rack->tags->pluck('name')->implode(', ') }}</span>
                        <span itemprop="applicationCategory">Music Production Software</span>
                        <span itemprop="operatingSystem">Windows, macOS</span>
                    </div>
                </div>

                <!-- Tags -->
                <div class="flex flex-wrap gap-3 mb-8">
                    @foreach($rack->tags as $tag)
                        <a href="{{ route('home') }}?tag={{ urlencode($tag->name) }}" 
                           class="badge-tag hover:bg-gray-200 transition-colors"
                           title="Browse more racks tagged with {{ $tag->name }}">
                            {{ $tag->name }}
                        </a>
                    @endforeach
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                    <div>
                        <div class="text-3xl font-bold text-black mb-1">{{ number_format($rack->downloads_count) }}</div>
                        <div class="text-sm text-gray-600">Downloads</div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold text-black mb-1">{{ number_format($rack->views_count) }}</div>
                        <div class="text-sm text-gray-600">Views</div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold text-black mb-1">{{ $rack->device_count }}</div>
                        <div class="text-sm text-gray-600">Devices</div>
                    </div>
                    <div>
                        <div class="text-3xl font-bold text-black mb-1">{{ $rack->chain_count }}</div>
                        <div class="text-sm text-gray-600">Chains</div>
                    </div>
                </div>

            </div>

            <!-- Action Buttons Row -->
            <div class="flex flex-col gap-4">
                <!-- Compact Icon Actions -->
                <div class="flex items-center gap-2">
                    <!-- Download Button -->
                    <button 
                        wire:click="downloadRack"
                        class="p-3 bg-white border-2 border-black rounded hover:shadow-lg transition-shadow"
                        title="Download Rack"
                    >
                        <svg class="w-6 h-6 download-btn" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4m4-5l5 5 5-5M12 3v12"></path>
                        </svg>
                    </button>

                    @auth
                        <!-- Favorite Button -->
                        <button 
                            wire:click="toggleFavorite"
                            class="p-3 bg-white border-2 border-black rounded hover:shadow-lg transition-shadow"
                            title="{{ $isFavorited ? 'Remove from Favorites' : 'Add to Favorites' }}"
                        >
                            <svg class="w-6 h-6 favorite-btn {{ $isFavorited ? 'active' : '' }}" fill="{{ $isFavorited ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                        </button>
                        
                        @if(auth()->id() === $rack->user_id)
                            <!-- Edit Button -->
                            <a 
                                href="{{ route('racks.edit', $rack) }}"
                                class="p-3 bg-white border-2 border-black rounded hover:shadow-lg transition-shadow inline-block"
                                title="Edit Rack"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            
                            <!-- Delete Button -->
                            <button 
                                wire:click="deleteRack"
                                wire:confirm="Are you sure you want to delete this rack? This action cannot be undone."
                                class="p-3 bg-white border-2 border-black rounded hover:shadow-lg transition-shadow hover:border-red-600"
                                title="Delete Rack"
                            >
                                <svg class="w-6 h-6 hover:text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        @else
                            <!-- Report Button -->
                            <button 
                                wire:click="openReportModal"
                                class="p-3 bg-white border-2 border-black rounded hover:shadow-lg transition-shadow hover:border-red-600"
                                title="Report Issue"
                            >
                                <svg class="w-6 h-6 hover:text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            </button>
                        @endif
                    @else
                        <!-- Login prompt for non-authenticated users -->
                        <div class="text-sm text-gray-600 italic ml-2">
                            <a href="{{ route('login') }}" class="link">Login</a> to interact
                        </div>
                    @endauth
                </div>
                
                <!-- Compact Rating System -->
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-black">Rate:</span>
                    <div class="flex items-center gap-1" x-data="{ showStars: false, hoveredStar: 0 }">
                        @auth
                            <div 
                                @mouseenter="showStars = true" 
                                @mouseleave="showStars = false; hoveredStar = 0"
                                class="flex items-center"
                            >
                                <!-- Collapsed state - show single star with rating -->
                                <div x-show="!showStars" class="flex items-center gap-2 cursor-pointer">
                                    <svg 
                                        class="w-6 h-6 star-btn {{ $userRating > 0 ? 'active' : '' }}" 
                                        fill="{{ $userRating > 0 ? 'currentColor' : 'none' }}"
                                        stroke="currentColor" 
                                        viewBox="0 0 24 24"
                                        stroke-width="2"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                    </svg>
                                    @if($userRating > 0)
                                        <span class="text-sm font-medium">{{ $userRating }}/5</span>
                                    @else
                                        <span class="text-sm text-gray-500">Rate</span>
                                    @endif
                                </div>
                                
                                <!-- Expanded state - show all 5 stars -->
                                <div x-show="showStars" x-transition:enter="transition ease-out duration-200" class="flex items-center gap-1">
                                    @for($i = 1; $i <= 5; $i++)
                                        <button
                                            wire:click="rateRack({{ $i }})"
                                            @mouseenter="hoveredStar = {{ $i }}"
                                            class="star-btn {{ ($userRating >= $i) ? 'active' : '' }} transition-all duration-150 hover:scale-110 transform"
                                        >
                                            <svg 
                                                class="w-6 h-6" 
                                                :fill="(hoveredStar >= {{ $i }} || {{ $userRating >= $i ? 'true' : 'false' }}) ? 'currentColor' : 'none'"
                                                stroke="currentColor" 
                                                viewBox="0 0 24 24"
                                                stroke-width="2"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                            </svg>
                                        </button>
                                    @endfor
                                </div>
                            </div>
                        @else
                            <span class="text-sm text-gray-600 italic">
                                <a href="{{ route('login') }}" class="link">Login</a> to rate
                            </span>
                        @endauth
                    </div>
                </div>
                
                <div class="text-sm text-gray-600">
                    File size: {{ number_format($rack->file_size / 1024, 1) }} KB
                </div>
            </div>
        </div>
    </div>

    <!-- Technical Details -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Rack Structure Tree View -->
        <div class="lg:col-span-2">
            <div class="card card-body">
                <h2 class="text-2xl font-bold mb-8 flex items-center gap-3 text-ableton-light">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                    </svg>
                    Device Chain
                </h2>
                
                <!-- Chain View Container -->
                <div class="" x-data="{ expandedChains: {}, expandAll: false }">
                    <!-- Expand/Collapse All Button -->
                    <div class="flex justify-between items-center mb-6">
                        <div class="text-sm text-ableton-light/70">
                            Click on chain headers to expand and see device details
                        </div>
                        <button 
                            @click="expandAll = !expandAll; Object.keys(expandedChains).forEach(key => expandedChains[key] = expandAll)"
                            class="btn-secondary text-sm flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                            </svg>
                            <span x-text="expandAll ? 'Collapse All' : 'Expand All'"></span>
                        </button>
                    </div>

                    <!-- Root Rack Node -->
                    <div class="mb-8">
                        <!-- Root Node -->
                        <div class="bg-ableton-black rounded-lg p-6 border-2 border-ableton-warning shadow-lg">
                            <div class="flex items-center gap-4">
                                <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-ableton-warning">
                                    <svg class="w-6 h-6 text-ableton-black" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="font-bold text-ableton-light text-lg">{{ $rack->title }}</span>
                                        <span class="text-xs px-3 py-1 rounded-full bg-ableton-gray text-ableton-light">
                                            {{ $rackData['type'] === 'AudioEffectGroupDevice' ? 'Audio Effect Rack' : ($rackData['type'] === 'InstrumentGroupDevice' ? 'Instrument Rack' : 'MIDI Effect Rack') }}
                                        </span>
                                    </div>
                                    @if(!empty($rackData['chains']))
                                        <div class="text-sm text-ableton-light/70">
                                            {{ count($rackData['chains']) }} {{ Str::plural('chain', count($rackData['chains'])) }} • 
                                            {{ collect($rackData['chains'])->sum(fn($chain) => count($chain['devices'])) }} total devices
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Clean Chain Layout -->
                        @if(!empty($rackData['chains']))
                            <div class="space-y-6 mt-8">
                                @foreach($rackData['chains'] as $chainIndex => $chain)
                                    <div class="border-2 border-black rounded-lg overflow-hidden" x-data="{ expanded: false, init() { this.$watch('expandAll', value => this.expanded = value) } }">
                                        <!-- Chain Header -->
                                        <div 
                                            class="bg-white p-4 cursor-pointer hover:bg-gray-50 transition-all border-b-2 border-black"
                                            @click="expanded = !expanded"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-4">
                                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-vibrant-purple">
                                                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <h3 class="font-semibold text-black">
                                                            @if(isset($rack->chain_annotations[$chainIndex]['custom_name']) && !empty($rack->chain_annotations[$chainIndex]['custom_name']))
                                                                {{ $rack->chain_annotations[$chainIndex]['custom_name'] }}
                                                                <span class="text-sm font-normal text-gray-600 ml-2">(Chain {{ $chainIndex + 1 }})</span>
                                                            @else
                                                                Chain {{ $chainIndex + 1 }}
                                                            @endif
                                                        </h3>
                                                        <p class="text-sm text-gray-600">
                                                            {{ count($chain['devices']) }} {{ Str::plural('device', count($chain['devices'])) }}
                                                        </p>
                                                    </div>
                                                </div>
                                                
                                                <!-- Expand Icon -->
                                                <svg 
                                                    class="w-5 h-5 text-black transform transition-transform duration-200" 
                                                    :class="expanded ? 'rotate-90' : ''"
                                                    fill="none" 
                                                    stroke="currentColor" 
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                </svg>
                                            </div>
                                            
                                            <!-- Chain Annotation Note -->
                                            @if(isset($rack->chain_annotations[$chainIndex]['note']) && !empty($rack->chain_annotations[$chainIndex]['note']))
                                                <div class="mt-4 p-3 bg-gray-100 rounded-lg border border-gray-300">
                                                    <div class="text-sm text-gray-700">
                                                        <span class="font-medium text-black">{{ $rack->user->name }} says:</span> 
                                                        {{ $rack->chain_annotations[$chainIndex]['note'] }}
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        
                                        <!-- Devices in Chain -->
                                        <div x-show="expanded" x-collapse class="bg-gray-50 border-t-2 border-black">
                                            @if(!empty($chain['devices']))
                                                <!-- Tree Structure -->
                                                <div class="p-6 space-y-4">
                                                    @foreach($chain['devices'] as $deviceIndex => $device)
                                                        <div class="relative">
                                                            <!-- Tree Line -->
                                                            <div class="flex items-start">
                                                                <!-- Tree connector -->
                                                                <div class="flex-shrink-0 w-8 h-8 relative">
                                                                    <!-- Vertical line (except for last item) -->
                                                                    @if($deviceIndex < count($chain['devices']) - 1)
                                                                        <div class="absolute left-3 top-8 w-px h-6 bg-ableton-light/30"></div>
                                                                    @endif
                                                                    <!-- Horizontal line -->
                                                                    <div class="absolute top-3 left-3 w-4 h-px bg-ableton-light/30"></div>
                                                                    <!-- Corner -->
                                                                    <div class="absolute top-3 left-3 w-px h-5 bg-ableton-light/30"></div>
                                                                    <!-- Node -->
                                                                    <div class="absolute top-2 left-2 w-2 h-2 bg-ableton-accent rounded-full"></div>
                                                                </div>
                                                                
                                                                <!-- Device Content -->
                                                                <div class="flex-1 min-w-0 ml-2">
                                                                    <div class="bg-white border-2 border-black rounded-lg p-4 hover:shadow-lg transition-shadow">
                                                                        <div class="flex items-center gap-3">
                                                                            <!-- Device Icon -->
                                                                            @if(isset($device['chains']) && !empty($device['chains']))
                                                                                <!-- Nested rack icon - simple black -->
                                                                                <svg class="device-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                                    <rect x="2" y="4" width="20" height="16" rx="2"/>
                                                                                    <path d="M7 8h10M7 12h4M7 16h6"/>
                                                                                </svg>
                                                                            @else
                                                                                <!-- Regular device icon - simple black -->
                                                                                <svg class="device-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                                                    <circle cx="12" cy="12" r="3"/>
                                                                                    <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
                                                                                </svg>
                                                                            @endif
                                                                            
                                                                            <!-- Device Details -->
                                                                            <div class="flex-1 min-w-0">
                                                                                <div class="font-medium text-black">
                                                                                    {{ $device['name'] ?? 'Unknown Device' }}
                                                                                </div>
                                                                                <div class="flex flex-wrap gap-2 mt-1">
                                                                                    @if(isset($device['preset']) && $device['preset'])
                                                                                        <span class="text-xs px-2 py-1 rounded bg-vibrant-red text-white">
                                                                                            {{ $device['preset'] }}
                                                                                        </span>
                                                                                    @endif
                                                                                    @if(isset($device['chains']) && !empty($device['chains']))
                                                                                        <span class="text-xs px-2 py-1 rounded bg-vibrant-cyan text-white">
                                                                                            {{ count($device['chains']) }} nested chains
                                                                                        </span>
                                                                                    @endif
                                                                                    @if(isset($device['type']))
                                                                                        <span class="text-xs text-gray-600">
                                                                                            {{ $device['type'] }}
                                                                                        </span>
                                                                                    @endif
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <!-- Nested devices (recursive tree) -->
                                                                    @if(isset($device['chains']) && !empty($device['chains']))
                                                                        <div class="ml-6 mt-4 space-y-3">
                                                                            @foreach($device['chains'] as $nestedChainIndex => $nestedChain)
                                                                                <div class="border-l-2 border-ableton-light/20 pl-4">
                                                                                    <div class="text-sm font-medium text-ableton-light/80 mb-2">
                                                                                        Nested Chain {{ $nestedChainIndex + 1 }}
                                                                                    </div>
                                                                                    @if(!empty($nestedChain['devices']))
                                                                                        <div class="space-y-2">
                                                                                            @foreach($nestedChain['devices'] as $nestedDeviceIndex => $nestedDevice)
                                                                                                <div class="flex items-start">
                                                                                                    <!-- Nested tree connector -->
                                                                                                    <div class="flex-shrink-0 w-6 h-6 relative">
                                                                                                        @if($nestedDeviceIndex < count($nestedChain['devices']) - 1)
                                                                                                            <div class="absolute left-2 top-6 w-px h-4 bg-ableton-light/20"></div>
                                                                                                        @endif
                                                                                                        <div class="absolute top-2 left-2 w-3 h-px bg-ableton-light/20"></div>
                                                                                                        <div class="absolute top-2 left-2 w-px h-4 bg-ableton-light/20"></div>
                                                                                                        <div class="absolute top-1.5 left-1.5 w-1.5 h-1.5 bg-ableton-accent rounded-full"></div>
                                                                                                    </div>
                                                                                                    
                                                                                                    <!-- Nested device -->
                                                                                                    <div class="flex-1 bg-ableton-light/5 border border-ableton-light/10 rounded p-3">
                                                                                                        <div class="flex items-center gap-2">
                                                                                                            <div class="w-4 h-4 rounded bg-ableton-success flex items-center justify-center">
                                                                                                                <div class="w-1.5 h-1.5 rounded-full bg-ableton-black"></div>
                                                                                                            </div>
                                                                                                            <span class="text-sm text-ableton-light">
                                                                                                                {{ $nestedDevice['name'] ?? 'Unknown' }}
                                                                                                            </span>
                                                                                                            @if(isset($nestedDevice['preset']) && $nestedDevice['preset'])
                                                                                                                <span class="text-xs px-1.5 py-0.5 rounded bg-ableton-danger text-ableton-black ml-auto">
                                                                                                                    {{ $nestedDevice['preset'] }}
                                                                                                                </span>
                                                                                                            @endif
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            @endforeach
                                                                                        </div>
                                                                                    @else
                                                                                        <div class="text-xs text-ableton-light/50 italic">Empty chain</div>
                                                                                    @endif
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="text-center text-ableton-light/60 text-sm py-6">
                                                    No devices in this chain
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Empty State -->
                @if(empty($rackData['chains']))
                    <div class="text-center py-12 text-ableton-light/60">
                        <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        <p class="text-sm">No device structure available</p>
                        <p class="text-xs mt-1">This rack may not have been fully analyzed</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Metadata Sidebar -->
        <div class="space-y-6">
            <!-- Technical Info -->
            <div class="card card-body">
                <h3 class="font-bold mb-4 text-ableton-light">Technical Details</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-ableton-light/60">Ableton Version:</span>
                        <span class="text-ableton-light font-medium">{{ $rack->ableton_version ?: 'Unknown' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ableton-light/60">File Size:</span>
                        <span class="text-ableton-light font-medium">{{ number_format($rack->file_size / 1024, 1) }} KB</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ableton-light/60">Published:</span>
                        <span class="text-ableton-light font-medium">
                            {{ $rack->published_at ? $rack->published_at->format('M d, Y') : 'Pending' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ableton-light/60">Status:</span>
                        <span class="badge-success">
                            {{ ucfirst($rack->status) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card card-body">
                <h3 class="font-bold mb-4 text-ableton-light">Activity</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-ableton-light/60">Last viewed:</span>
                        <span class="text-ableton-light font-medium">{{ $rack->updated_at->diffForHumans() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ableton-light/60">Comments:</span>
                        <span class="text-ableton-light font-medium">{{ $rack->comments_count }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ableton-light/60">Likes:</span>
                        <span class="text-ableton-light font-medium">{{ $rack->likes_count }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Modal -->
    @if($showReportModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-75">
            <div class="max-w-md w-full card card-body">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-ableton-light">Report Issue</h3>
                    <button wire:click="closeReportModal" class="text-ableton-light/60 hover:text-ableton-light">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="submitReport">
                    <!-- Issue Type -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2 text-ableton-light">
                            What's wrong with this rack?
                        </label>
                        <select 
                            wire:model="reportIssueType" 
                            class="input-field"
                        >
                            <option value="">Select an issue...</option>
                            @foreach(\App\Models\RackReport::getIssueTypes() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('reportIssueType') 
                            <span class="text-sm mt-1 block text-ableton-danger">{{ $message }}</span> 
                        @enderror
                    </div>

                    <!-- Description -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-2 text-ableton-light">
                            Please describe the issue
                        </label>
                        <textarea 
                            wire:model="reportDescription" 
                            rows="4"
                            placeholder="Please provide details about the problem you encountered..."
                            class="input-field"
                        ></textarea>
                        @error('reportDescription') 
                            <span class="text-sm mt-1 block text-ableton-danger">{{ $message }}</span> 
                        @enderror
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3">
                        <button 
                            type="button"
                            wire:click="closeReportModal"
                            class="btn-secondary flex-1"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            class="btn-danger flex-1"
                        >
                            Submit Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Flash Messages -->
    @if(session()->has('success'))
        <div class="card card-body bg-ableton-success text-ableton-black mb-4">
            {{ session('success') }}
        </div>
    @endif
    
    @if(session()->has('error'))
        <div class="card card-body bg-ableton-danger text-ableton-black mb-4">
            {{ session('error') }}
        </div>
    @endif

    {{-- Internal Linking for SEO --}}
    <x-internal-links :rack="$rack" />
    
    {{-- Additional SEO content --}}
    <div class="sr-only">
        <h2>How to Use This {{ ucfirst($rack->rack_type) }} Rack</h2>
        <p>To use this {{ $rack->rack_type }} rack in Ableton Live:</p>
        <ol>
            <li>Download the .adg file</li>
            <li>Open Ableton Live {{ $rack->ableton_version ?? '9+' }}</li>
            <li>Drag the file into your Live Set</li>
            <li>Start creating music with this {{ $rack->category }} rack</li>
        </ol>
        
        <h3>About the Creator</h3>
        <p>{{ $rack->user->name }} is a talented music producer sharing quality Ableton Live content. Discover more racks and follow their work on Ableton Cookbook.</p>
        
        <h3>Similar Music Production Content</h3>
        <p>If you enjoyed this {{ $rack->rack_type }} rack, explore more {{ $rack->category }} content, browse racks by {{ $rack->user->name }}, or discover other {{ $rack->rack_type }} racks in our community.</p>
    </div>
</div>