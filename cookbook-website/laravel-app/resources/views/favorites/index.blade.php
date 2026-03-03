<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-black leading-tight">
            My Favorites
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Page title + count --}}
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-black">My Favorites</h1>
                    <p class="text-gray-600 mt-1">
                        {{ $favorites->total() }} {{ Str::plural('rack', $favorites->total()) }} saved
                    </p>
                </div>
                <a href="{{ route('home') }}"
                    class="inline-flex items-center px-4 py-2 bg-vibrant-purple text-white font-semibold border-2 border-black rounded hover:shadow-lg transition-shadow">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    Browse Racks
                </a>
            </div>

            {{-- Flash message --}}
            @if(session('message'))
            <div class="mb-6 p-4 bg-green-50 border-2 border-green-500 rounded text-green-800 font-medium">
                {{ session('message') }}
            </div>
            @endif

            {{-- Rack grid --}}
            @if($favorites->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach($favorites as $favorite)
                @php $rack = $favorite->rack @endphp
                @if($rack)
                <a href="{{ route('racks.show', $rack) }}"
                    class="card hover:shadow-lg transition-all cursor-pointer h-full flex flex-col group">

                    <div class="p-4 flex flex-col flex-1">
                        {{-- Title --}}
                        <h3 class="font-bold text-black text-base mb-1 line-clamp-2 group-hover:text-vibrant-purple transition-colors">
                            {{ $rack->title }}
                        </h3>

                        {{-- Uploader --}}
                        <p class="text-xs text-gray-500 mb-3">
                            by {{ $rack->user->name ?? 'Unknown' }}
                        </p>

                        {{-- Description --}}
                        @if($rack->description)
                        <p class="text-sm text-gray-600 line-clamp-2 mb-3 flex-1">
                            {{ $rack->description }}
                        </p>
                        @else
                        <div class="flex-1"></div>
                        @endif

                        {{-- Meta row --}}
                        <div class="flex items-center justify-between mt-auto pt-3 border-t border-gray-100">
                            <div class="flex items-center space-x-2 text-gray-500 text-xs">
                                {{-- Rack type icon --}}
                                @if($rack->rack_type === 'InstrumentGroupDevice')
                                <i class="fa-duotone fa-thin fa-piano-keyboard" style="font-size:16px;" title="Instrument"></i>
                                @elseif($rack->rack_type === 'AudioEffectGroupDevice')
                                <i class="fa-thin fa-dial-med" style="font-size:16px;" title="Audio Effect"></i>
                                @elseif($rack->rack_type === 'MidiEffectGroupDevice')
                                <i class="fa-thin fa-file-midi" style="font-size:16px;" title="MIDI Effect"></i>
                                @elseif($rack->rack_type === 'drum_rack')
                                <i class="fa-solid fa-drum" style="font-size:16px;" title="Drum Rack"></i>
                                @endif

                                {{-- Ableton edition --}}
                                <i class="fa-kit fa-circle-ableton"
                                    style="color: {{ $rack->ableton_edition === 'suite' ? '#f97316' : ($rack->ableton_edition === 'standard' ? '#3b82f6' : '#6b7280') }}; font-size:16px;"
                                    title="Live {{ ucfirst($rack->ableton_edition ?? 'Unknown') }}"></i>
                            </div>

                            <div class="flex items-center space-x-2 text-xs text-gray-400">
                                {{-- Downloads --}}
                                <span title="Downloads">
                                    <svg class="w-3 h-3 inline mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    {{ $rack->downloads_count ?? 0 }}
                                </span>
                                {{-- Rating --}}
                                @if($rack->ratings_count > 0)
                                <span title="Rating">
                                    <svg class="w-3 h-3 inline mr-0.5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                    {{ number_format($rack->average_rating, 1) }}
                                </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </a>
                @endif
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($favorites->hasPages())
            <div class="mt-8">
                {{ $favorites->links() }}
            </div>
            @endif

            @else
            {{-- Empty state --}}
            <div class="text-center py-24">
                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
                <h3 class="text-xl font-bold text-black mb-2">No favorites yet</h3>
                <p class="text-gray-600 mb-6">
                    Browse racks and click the heart icon to save your favourites here.
                </p>
                <a href="{{ route('home') }}"
                    class="inline-flex items-center px-6 py-3 bg-vibrant-purple text-white font-semibold border-2 border-black rounded hover:shadow-lg transition-shadow">
                    Browse Racks
                </a>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>