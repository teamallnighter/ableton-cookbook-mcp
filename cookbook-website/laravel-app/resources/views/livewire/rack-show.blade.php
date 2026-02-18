<div>
    <!-- Skip link for keyboard navigation -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <main id="main-content" class="max-w-6xl mx-auto px-6 py-8" role="main" aria-labelledby="page-title">
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
                        <h1 class="text-4xl font-bold mb-4 text-black break-words" itemprop="name">{{ $rack->title }}</h1>
                        <p class="text-lg text-gray-700">
                            by 
                            <a 
                                href="{{ route('users.show', $rack->user) }}" 
                                class="link break-words"
                            >
                                {{ $rack->user->name }}
                            </a>
                        </p>
                    </div>
                    
                    <div class="flex items-center gap-3 ml-4">
                        <!-- Edition Badge -->
                        @if($rack->ableton_edition)
                            <i class="fa-kit fa-circle-ableton" style="color: {{ $rack->ableton_edition === 'suite' ? '#f97316' : ($rack->ableton_edition === 'standard' ? '#3b82f6' : '#6b7280') }}; font-size: 18px;" title="Live {{ ucfirst($rack->ableton_edition) }}"></i>
                        @endif
                        
                        {{-- Rack Type Badge --}}
                        @if($rack->rack_type)
                            @if($rack->rack_type === 'InstrumentGroupDevice')
                                <i class="fa-duotone fa-thin fa-piano-keyboard" style="font-size: 18px;" title="Instrument"></i>
                            @elseif($rack->rack_type === 'AudioEffectGroupDevice')
                                <i class="fa-thin fa-dial-med" style="font-size: 18px;" title="Audio Effect"></i>
                            @elseif($rack->rack_type === 'MidiEffectGroupDevice')
                                <i class="fa-thin fa-file-midi" style="font-size: 18px;" title="MIDI Effect"></i>
                            @endif
                        @endif
                        
                        {{-- Category Badge --}}
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
                    <div itemprop="description" class="text-lg leading-relaxed text-gray-800 break-words">{{ $rack->description }}</div>
                    
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
                            <a href="{{ route('login') }}" class="link break-words">Login</a> to interact
                        </div>
                    @endauth
                </div>
                
                <!-- Compact Rating System -->
                <div class="flex items-center gap-3" role="group" aria-labelledby="rating-label">
                    <span id="rating-label" class="text-sm font-medium text-black">Rate this rack:</span>
                    <div class="flex items-center gap-1"
                         x-data="{ showStars: false, hoveredStar: 0 }">
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
                                <a href="{{ route('login') }}" class="link break-words">Login</a> to rate
                            </span>
                        @endauth
                    </div>
                </div>
                
                <div class="text-sm text-gray-600">
                    File size: {{ number_format($rack->file_size / 1024, 1) }} KB
                </div>
            </section>
        </div>
    </div>

    <!-- Stacked Content Layout -->
    <div class="space-y-8">
        <!-- How-To Article Section (Full Width) -->
        <div>
            @if($rack->how_to_article)
                <div class="card card-body">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-black">How to Use This Rack</h2>
                        <div class="text-sm text-gray-500">
                            @if($rack->how_to_updated_at)
                                Updated {{ $rack->how_to_updated_at->diffForHumans() }}
                            @endif
                        </div>
                    </div>
                    
                    <div class="prose prose-lg max-w-none break-words">
                        {!! $rack->html_how_to !!}
                    </div>
                    
                    @if($rack->how_to_preview && strlen($rack->how_to_article) > strlen($rack->how_to_preview))
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <p class="text-sm text-gray-600 italic">
                                📖 {{ $rack->reading_time_how_to }} min read
                            </p>
                        </div>
                    @endif
                </div>
            @else
                <!-- No How-To Article -->
                <div class="card card-body text-center py-12">
                    <div class="max-w-md mx-auto">
                        <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No How-To Guide Available</h3>
                        <p class="text-gray-600 mb-4">This rack doesn't have a detailed how-to guide yet. The device structure is available below.</p>
                        @if(auth()->check() && auth()->id() === $rack->user_id)
                            <a href="{{ route('racks.edit', $rack) }}" class="btn btn-primary">
                                Add How-To Guide
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Rack Visualization Section (Full Width) -->
        <div>
            {{-- Unified Rack Visualization --}}
            <div class="card card-body" x-data="{ activeTab: 'diagram' }">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-black">Rack Visualization</h3>
                        
                        {{-- Visualization Mode Tabs --}}
                        <div class="flex bg-gray-100 rounded-lg p-1">
                            <button class="px-3 py-1 text-sm rounded-md transition-colors"
                                    :class="{ 'bg-white shadow-sm text-vibrant-purple': activeTab === 'diagram', 'text-gray-600 hover:text-gray-900': activeTab !== 'diagram' }"
                                    @click="activeTab = 'diagram'">
                                <i class="fas fa-diagram-project mr-1"></i> D2 Diagram
                            </button>
                            <button class="px-3 py-1 text-sm rounded-md transition-colors"
                                    :class="{ 'bg-white shadow-sm text-vibrant-purple': activeTab === 'tree', 'text-gray-600 hover:text-gray-900': activeTab !== 'tree' }"
                                    @click="activeTab = 'tree'">
                                <i class="fas fa-sitemap mr-1"></i> Tree View
                            </button>
                            <button class="px-3 py-1 text-sm rounded-md transition-colors"
                                    :class="{ 'bg-white shadow-sm text-vibrant-purple': activeTab === 'cards', 'text-gray-600 hover:text-gray-900': activeTab !== 'cards' }"
                                    @click="activeTab = 'cards'">
                                <i class="fas fa-th-large mr-1"></i> Card View
                            </button>
                            <button class="px-3 py-1 text-sm rounded-md transition-colors"
                                    :class="{ 'bg-white shadow-sm text-vibrant-purple': activeTab === 'ascii', 'text-gray-600 hover:text-gray-900': activeTab !== 'ascii' }"
                                    @click="activeTab = 'ascii'">
                                <i class="fas fa-terminal mr-1"></i> ASCII
                            </button>
                        </div>
                    </div>
                    
                    {{-- D2 Diagram Tab --}}
                    <div x-show="activeTab === 'diagram'" x-transition>
                        <div class="mb-3">
                            <select wire:model.live="currentDiagramStyle"
                                    class="text-sm border-gray-300 rounded-md focus:ring-vibrant-purple focus:border-vibrant-purple">
                                @foreach($availableStyles as $style)
                                    <option value="{{ $style }}">{{ ucfirst($style) }} Style</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="border border-gray-200 rounded-lg p-6 bg-gray-50 overflow-x-auto">
                            @if($diagram = $this->getDiagramSvg())
                                <div class="flex justify-center">
                                    {!! $diagram !!}
                                </div>
                            @else
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-diagram-project text-4xl mb-3"></i>
                                    <p>Diagram generation in progress...</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    {{-- Tree View Tab --}}
                    <div x-show="activeTab === 'tree'" x-transition x-data="{ treeViewLoaded: true }">
                        <div class="border border-gray-200 rounded-lg p-4 bg-white">
                            @if(!empty($rackData['chains']))
                                <div class="tree-structure">
                                    @foreach($rackData['chains'] as $chainIndex => $chain)
                                        <div class="chain-branch mb-3" x-data="{ expanded: true }">
                                            <div class="flex items-center cursor-pointer hover:bg-gray-50 p-2 rounded" @click="expanded = !expanded">
                                                <svg class="w-4 h-4 mr-2 transition-transform duration-500 ease-in-out" :class="expanded ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                                <span class="font-semibold text-sm">{{ $chain['name'] ?? "Chain " . ($chainIndex + 1) }}</span>
                                                <span class="ml-auto text-xs text-gray-500">{{ count($chain['devices'] ?? []) }} devices</span>
                                            </div>
                                            
                                            <div x-show="expanded" 
                                                 x-transition:enter="transition ease-out duration-500"
                                                 x-transition:enter-start="opacity-0 max-h-0"
                                                 x-transition:enter-end="opacity-100 max-h-screen"
                                                 x-transition:leave="transition ease-in duration-500"
                                                 x-transition:leave-start="opacity-100 max-h-screen"
                                                 x-transition:leave-end="opacity-0 max-h-0"
                                                 class="ml-6 mt-1 overflow-hidden">
                                                @if(!empty($chain['devices']))
                                                    @foreach($chain['devices'] as $device)
                                                        <div class="device-leaf flex items-center py-1 px-2 text-sm hover:bg-gray-50 rounded">
                                                            <svg class="w-3 h-3 mr-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                                <circle cx="10" cy="10" r="3"/>
                                                            </svg>
                                                            <span class="text-gray-700">{{ $device['display_name'] ?? $device['name'] ?? $device['standard_name'] ?? 'Unknown Device' }}</span>
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <div class="text-xs text-gray-500 italic px-2">No devices</div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center text-gray-500 text-sm py-6">
                                    <p>No device structure available for this rack</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    {{-- Card View Tab --}}
                    <div x-show="activeTab === 'cards'" x-transition>
                        <div class="border border-gray-200 rounded-lg p-4 bg-white">
                            @if(!empty($rackData['chains']))
                                <div class="space-y-3">
                                    @foreach($rackData['chains'] as $chainIndex => $chain)
                                        <div class="bg-gray-50 rounded-lg p-3">
                                            <div class="flex items-center gap-2 mb-2">
                                                <div class="w-3 h-3 rounded-full bg-vibrant-purple"></div>
                                                <div class="text-sm font-medium text-black">
                                                    @if(isset($rack->chain_annotations[$chainIndex]['custom_name']) && !empty($rack->chain_annotations[$chainIndex]['custom_name']))
                                                        {{ $rack->chain_annotations[$chainIndex]['custom_name'] }}
                                                    @else
                                                        Chain {{ $chainIndex + 1 }}
                                                    @endif
                                                </div>
                                            </div>
                                            @if(!empty($chain['devices']))
                                                <div class="space-y-1">
                                                    @foreach($chain['devices'] as $device)
                                                        <div class="flex items-center gap-2 text-xs text-gray-700">
                                                            <div class="w-1 h-1 rounded-full bg-gray-400"></div>
                                                            {{ $device['display_name'] ?? $device['name'] ?? $device['standard_name'] ?? 'Unknown Device' }}
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center text-gray-500 text-sm py-6">
                                    <p>No device structure available for this rack</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    {{-- ASCII View Tab --}}
                    <div x-show="activeTab === 'ascii'" x-transition>
                        <div class="flex items-center justify-end mb-3">
                            <button wire:click="copyAsciiDiagram"
                                    class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors">
                                <i class="fas fa-copy mr-1"></i> Copy ASCII
                            </button>
                        </div>
                        <div class="bg-black text-green-400 rounded-lg p-4 font-mono text-sm overflow-x-auto">
                            @if($asciiDiagram = $this->getAsciiDiagram())
                                <pre class="whitespace-pre leading-tight m-0">{{ $asciiDiagram }}</pre>
                            @else
                                <div class="text-center py-8">
                                    <i class="fas fa-terminal text-4xl mb-3"></i>
                                    <p>ASCII diagram generation in progress...</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                
                @if($this->isDrumRack())
                    {{-- Drum Rack Specific Visualization --}}
                    <div class="card card-body mb-6">
                        <x-drum-rack-visualizer :drumRackData="$this->getDrumRackData()" />
                    </div>
                @endif
        </div>
    </div>
    </main>
</div>

@push('scripts')
<script>
    // Listen for copy-to-clipboard event from Livewire
    window.addEventListener('copy-to-clipboard', event => {
        console.log('Copy event received:', event);
        const text = event.detail.text;
        console.log('Text to copy length:', text ? text.length : 'null');

        if (text) {
            // Check if clipboard API is available
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    // Show success message
                    alert('ASCII diagram copied to clipboard!');
                }).catch(err => {
                    console.error('Failed to copy text: ', err);
                    // Fallback for clipboard API failure
                    fallbackCopyTextToClipboard(text);
                });
            } else {
                // Fallback for older browsers or non-HTTPS
                fallbackCopyTextToClipboard(text);
            }
        } else {
            console.error('No text provided to copy');
            alert('No ASCII diagram available to copy.');
        }
    });

    // Fallback copy method for browsers without clipboard API
    function fallbackCopyTextToClipboard(text) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            const successful = document.execCommand('copy');
            if (successful) {
                alert('ASCII diagram copied to clipboard!');
            } else {
                alert('Failed to copy to clipboard. Please try manually selecting and copying.');
            }
        } catch (err) {
            console.error('Fallback copy failed: ', err);
            alert('Failed to copy to clipboard. Please try manually selecting and copying.');
        }

        document.body.removeChild(textArea);
    }
</script>
@endpush
