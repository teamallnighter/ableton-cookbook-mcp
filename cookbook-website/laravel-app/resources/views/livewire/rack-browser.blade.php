<div class="max-w-7xl mx-auto px-6 py-8">
    <!-- Header -->
    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-black mb-4">Discover Racks</h1>
        <p class="text-gray-700">Share and explore amazing Ableton Live racks from the community</p>
    </div>

    <!-- Search and Filters -->
    <div class="card card-body mb-12" x-data="{ filtersOpen: false }">
        <div class="flex flex-col lg:flex-row gap-4 items-stretch">
            <!-- Search -->
            <div class="flex-1 lg:min-w-[400px]">
                <input 
                    type="text" 
                    wire:model="search"
                    wire:keyup.debounce.300ms="$refresh"
                    placeholder="Search racks..." 
                    class="input-field text-lg w-full"
                >
            </div>

            <!-- Sort -->
            <div class="lg:w-48">
                <select 
                    wire:model="sortBy" 
                    wire:change="$refresh"
                    class="input-field w-full"
                >
                    @foreach($sortOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filters Toggle -->
            <button 
                @click="filtersOpen = !filtersOpen"
                class="btn-secondary flex items-center justify-center gap-2 whitespace-nowrap"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                Filters
            </button>
        </div>

        <!-- Advanced Filters -->
        <div x-show="filtersOpen" x-collapse class="mt-8 pt-8 border-t-2 border-black">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Rack Type Filter -->
                <div>
                    <label class="block text-sm font-medium mb-3 text-black">Type</label>
                    <select wire:model="selectedRackType" wire:change="$refresh" class="input-field">
                        <option value="">All Types</option>
                        @foreach($rackTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Category Filter -->
                <div>
                    <label class="block text-sm font-medium mb-3 text-black">Category</label>
                    <select wire:model="selectedCategory" wire:change="$refresh" class="input-field">
                        <option value="">All Categories</option>
                        @foreach($categories as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @if($selectedRackType)
                        <p class="text-xs mt-2 text-gray-600">
                            Showing categories for {{ $rackTypes[$selectedRackType] ?? $selectedRackType }}
                        </p>
                    @else
                        <p class="text-xs mt-2 text-gray-600">
                            Select a rack type above to see specific categories
                        </p>
                    @endif
                </div>

                <!-- Ableton Edition Filter -->
                <div>
                    <label class="block text-sm font-medium mb-3 text-black">Your Edition</label>
                    <select wire:model="selectedEdition" wire:change="$refresh" class="input-field">
                        <option value="">All Editions</option>
                        <option value="intro" title="Shows only racks that work with Live Intro">Live Intro</option>
                        <option value="standard" title="Shows racks that work with Live Standard (includes Intro racks)">Live Standard</option>
                        <option value="suite" title="Shows all racks">Live Suite</option>
                    </select>
                    @if($selectedEdition)
                        <p class="text-xs mt-2 text-gray-600">
                            @if($selectedEdition === 'intro')
                                Shows Intro-compatible racks only
                            @elseif($selectedEdition === 'standard')
                                Shows Intro & Standard racks
                            @else
                                Shows all racks
                            @endif
                        </p>
                    @endif
                </div>

                <!-- Clear Filters -->
                <div class="flex flex-col justify-end gap-3">
                    <button wire:click="clearFilters" class="btn-secondary">
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Summary -->
    <div class="flex justify-between items-center mb-8">
        <p class="text-gray-700 text-lg">
            <span class="font-bold text-black">{{ $racks->total() }}</span> racks found
            @if($search)
                for "{{ $search }}"
            @endif
        </p>
        
        <!-- Loading indicator -->
        <div wire:loading class="flex items-center text-black">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Loading...
        </div>
    </div>

    <!-- Racks Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8 mb-12">
        @forelse($racks as $rack)
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
                        

                    </div>
                </div>

                <!-- Rack Content -->
                <div class="p-4 flex-1 flex flex-col">
                    <!-- Description -->
                    <p class="text-sm mb-4 line-clamp-2 flex-1 text-gray-700 leading-relaxed">{{ $rack->description }}</p>
                    
                    <!-- Tags -->
                    @if($rack->tags->count() > 0)
                        <div class="mb-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach($rack->tags->take(3) as $tag)
                                    <span class="badge-tag-small">{{ $tag->name }}</span>
                                @endforeach
                                @if($rack->tags->count() > 3)
                                    <span class="text-xs text-gray-500">+{{ $rack->tags->count() - 3 }} more</span>
                                @endif
                            </div>
                        </div>
                    @endif
                    
                    <!-- Bottom Info -->
                    <div class="mt-auto">
                        <!-- Badges Row -->
                        <div class="flex justify-between items-center w-full">
                            <!-- Ableton Edition -->
                            @if($rack->ableton_edition)
                                <i class="fa-kit fa-circle-ableton" style="color: {{ $rack->ableton_edition === 'suite' ? '#f97316' : ($rack->ableton_edition === 'standard' ? '#3b82f6' : '#6b7280') }}; font-size: 18px;" title="Live {{ ucfirst($rack->ableton_edition) }}"></i>
                            @endif
                            
                            {{-- Rack Type --}}
                            @if($rack->rack_type)
                                @if($rack->rack_type === 'InstrumentGroupDevice')
                                    <i class="fa-duotone fa-thin fa-piano-keyboard" style="font-size: 18px;" title="Instrument"></i>
                                @elseif($rack->rack_type === 'AudioEffectGroupDevice')
                                    <i class="fa-thin fa-dial-med" style="font-size: 18px;" title="Audio Effect"></i>
                                @elseif($rack->rack_type === 'MidiEffectGroupDevice')
                                    <i class="fa-thin fa-file-midi" style="font-size: 18px;" title="MIDI Effect"></i>
                                @endif
                            @endif
                            
                            <!-- Category Icon -->
                            @if($rack->category)
                                @if($rack->category === 'dynamics')
                                    <i class="fa-solid fa-wave-square" style="font-size: 18px;" title="Dynamics"></i>
                                @elseif($rack->category === 'time-based')
                                    <i class="fa-solid fa-clock" style="font-size: 18px;" title="Time Based"></i>
                                @elseif($rack->category === 'modulation')
                                    <i class="fa-solid fa-wave-sine" style="font-size: 18px;" title="Modulation"></i>
                                @elseif($rack->category === 'spectral')
                                    <i class="fa-solid fa-chart-line" style="font-size: 18px;" title="Spectral"></i>
                                @elseif($rack->category === 'filters')
                                    <i class="fa-solid fa-filter" style="font-size: 18px;" title="Filters"></i>
                                @elseif($rack->category === 'creative-effects')
                                    <i class="fa-solid fa-sparkles" style="font-size: 18px;" title="Creative Effects"></i>
                                @elseif($rack->category === 'utility')
                                    <i class="fa-solid fa-wrench" style="font-size: 18px;" title="Utility"></i>
                                @elseif($rack->category === 'mixing')
                                    <i class="fa-solid fa-sliders" style="font-size: 18px;" title="Mixing"></i>
                                @elseif($rack->category === 'distortion')
                                    <i class="fa-solid fa-bolt" style="font-size: 18px;" title="Distortion"></i>
                                @elseif($rack->category === 'drums')
                                    <i class="fa-solid fa-drum" style="font-size: 18px;" title="Drums"></i>
                                @elseif($rack->category === 'samplers')
                                    <i class="fa-solid fa-compact-disc" style="font-size: 18px;" title="Samplers"></i>
                                @elseif($rack->category === 'synths')
                                    <i class="fa-solid fa-sine-wave" style="font-size: 18px;" title="Synths"></i>
                                @elseif($rack->category === 'bass')
                                    <i class="fa-solid fa-guitar" style="font-size: 18px;" title="Bass"></i>
                                @elseif($rack->category === 'fx')
                                    <i class="fa-solid fa-magic-wand-sparkles" style="font-size: 18px;" title="FX"></i>
                                @elseif($rack->category === 'arpeggiators-sequencers')
                                    <i class="fa-solid fa-repeat" style="font-size: 18px;" title="Arpeggiators & Sequencers"></i>
                                @elseif($rack->category === 'music-theory')
                                    <i class="fa-solid fa-music" style="font-size: 18px;" title="Music Theory"></i>
                                @elseif($rack->category === 'other')
                                    <i class="fa-solid fa-ellipsis" style="font-size: 18px;" title="Other"></i>
                                @else
                                    <i class="fa-solid fa-tag" style="font-size: 18px;" title="{{ ucfirst(str_replace('-', ' ', $rack->category)) }}"></i>
                                @endif
                            @endif
                            
                            <!-- Heart/Favorite -->
                            @auth
                                <button 
                                    wire:click="toggleFavorite({{ $rack->id }})"
                                    onclick="event.stopPropagation();"
                                    class="hover:scale-110 transition-transform"
                                    title="{{ $rack->is_favorited_by_user ? 'Remove from favorites' : 'Add to favorites' }}"
                                >
                                    <i class="fa-{{ $rack->is_favorited_by_user ? 'solid' : 'regular' }} fa-heart" style="font-size: 18px; color: {{ $rack->is_favorited_by_user ? '#e11d48' : 'currentColor' }};" title="{{ $rack->is_favorited_by_user ? 'Remove from favorites' : 'Add to favorites' }}"></i>
                                </button>
                            @endauth
                            
                            <!-- Rating -->
                            @if($rack->average_rating > 0)
                                <div class="flex items-center gap-1">
                                    <i class="fa-solid fa-star" style="font-size: 18px; color: #fbbf24;" title="Rating: {{ number_format($rack->average_rating, 1) }} stars"></i>
                                    <span class="text-sm font-medium text-gray-700">{{ number_format($rack->average_rating, 1) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-20">
                <div class="text-8xl mb-6 text-ableton-light/30 opacity-50">🎛️</div>
                <h3 class="text-xl font-semibold mb-3 text-ableton-light">No racks found</h3>
                <p class="text-ableton-light/60">Try adjusting your search or filters</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($racks->hasPages())
        <div class="flex justify-center mt-12">
            {{ $racks->links() }}
        </div>
    @endif
</div>
