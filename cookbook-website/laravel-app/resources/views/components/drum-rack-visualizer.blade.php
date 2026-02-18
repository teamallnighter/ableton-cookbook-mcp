{{-- Drum Rack Visualizer Component --}}
@props([
    'drumRackData',
    'viewMode' => 'grid', // grid, list, performance
])

<div class="drum-rack-visualizer" 
     x-data="{ 
        viewMode: '{{ $viewMode }}',
        selectedPad: null,
        showPerformanceDetails: false,
        activePads: @js(collect($drumRackData['drum_chains'] ?? [])->where('devices', '!=', [])->count()),
        totalPads: @js(count($drumRackData['drum_chains'] ?? []))
     }"
     x-init="$el._alpineInstance = $data">

    {{-- Header with stats and view controls --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-4">
            <h3 class="text-lg font-medium text-black flex items-center gap-2">
                <i class="fa-solid fa-drum text-lg" title="Drum Rack"></i>
                Drum Rack Visualization
            </h3>
            
            {{-- Quick stats --}}
            <div class="flex items-center gap-3 text-sm text-gray-600">
                <span class="flex items-center gap-1">
                    <div class="w-2 h-2 rounded-full bg-green-500"></div>
                    <span x-text="activePads"></span> Active
                </span>
                <span class="flex items-center gap-1">
                    <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                    <span x-text="totalPads - activePads"></span> Empty
                </span>
                @if(isset($drumRackData['performance_analysis']))
                <span class="flex items-center gap-1">
                    <i class="fa-solid fa-gauge text-xs"></i>
                    {{ $drumRackData['performance_analysis']['complexity_score'] }}/100
                </span>
                @endif
            </div>
        </div>

        {{-- View mode switcher --}}
        <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1">
            <button @click="viewMode = 'grid'" 
                    :class="viewMode === 'grid' ? 'bg-white shadow-sm text-black' : 'text-gray-600 hover:text-black'"
                    class="px-3 py-1 text-sm rounded transition-all"
                    title="Pad Grid View">
                <i class="fa-solid fa-grid-2-plus"></i>
            </button>
            <button @click="viewMode = 'list'" 
                    :class="viewMode === 'list' ? 'bg-white shadow-sm text-black' : 'text-gray-600 hover:text-black'"
                    class="px-3 py-1 text-sm rounded transition-all"
                    title="List View">
                <i class="fa-solid fa-list"></i>
            </button>
            @if(isset($drumRackData['performance_analysis']))
            <button @click="viewMode = 'performance'" 
                    :class="viewMode === 'performance' ? 'bg-white shadow-sm text-black' : 'text-gray-600 hover:text-black'"
                    class="px-3 py-1 text-sm rounded transition-all"
                    title="Performance View">
                <i class="fa-solid fa-chart-line"></i>
            </button>
            @endif
        </div>
    </div>

    {{-- Grid View: 4x4 Drum Pad Layout --}}
    <div x-show="viewMode === 'grid'" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         class="drum-pad-grid">
        
        {{-- Generate 4x4 grid representing typical drum rack layout --}}
        <div class="grid grid-cols-4 gap-2 bg-gray-50 p-4 rounded-lg">
            @php
                $standardMidiNotes = [
                    60, 61, 62, 63,  // C4, C#4, D4, D#4
                    56, 57, 58, 59,  // G#3, A3, A#3, B3
                    52, 53, 54, 55,  // E3, F3, F#3, G3
                    48, 49, 50, 51   // C3, C#3, D3, D#3
                ];
                
                // Create lookup map for chain data by MIDI note
                $chainsByNote = [];
                foreach ($drumRackData['drum_chains'] ?? [] as $chain) {
                    $midiNote = $chain['drum_annotations']['midi_note'] ?? null;
                    if ($midiNote) {
                        $chainsByNote[$midiNote] = $chain;
                    }
                }
            @endphp
            
            @foreach($standardMidiNotes as $index => $midiNote)
                @php
                    $chain = $chainsByNote[$midiNote] ?? null;
                    $hasDevices = $chain && !empty($chain['devices']);
                    $drumType = $chain['drum_annotations']['drum_type'] ?? null;
                    $padName = $chain['name'] ?? "Pad " . ($midiNote);
                @endphp
                
                <div class="drum-pad aspect-square relative group cursor-pointer transition-all duration-200 hover:scale-105"
                     :class="selectedPad === {{ $midiNote }} ? 'ring-2 ring-ableton-accent' : ''"
                     @click="selectedPad = selectedPad === {{ $midiNote }} ? null : {{ $midiNote }}"
                     title="{{ $padName }} (MIDI {{ $midiNote }})">
                    
                    {{-- Pad background with status indicator --}}
                    <div class="w-full h-full rounded-lg flex items-center justify-center text-white font-medium text-xs
                                {{ $hasDevices ? 'bg-gradient-to-br from-green-500 to-green-600 shadow-md' : 'bg-gray-300 border-2 border-dashed border-gray-400' }}">
                        
                        @if($hasDevices)
                            {{-- Active pad with device count --}}
                            <div class="text-center">
                                <div class="text-lg font-bold">{{ $midiNote }}</div>
                                <div class="text-xs opacity-90">{{ count($chain['devices']) }} dev</div>
                            </div>
                            
                            {{-- Device type indicator --}}
                            <div class="absolute top-1 right-1">
                                @if(collect($chain['devices'])->contains('drum_context.is_drum_synthesizer', true))
                                    <i class="fa-solid fa-waveform text-xs" title="Synthesized"></i>
                                @elseif(collect($chain['devices'])->contains('drum_context.is_sampler', true))
                                    <i class="fa-solid fa-compact-disc text-xs" title="Sample-based"></i>
                                @endif
                            </div>
                            
                        @else
                            {{-- Empty pad --}}
                            <div class="text-gray-500 text-sm">{{ $midiNote }}</div>
                        @endif
                    </div>
                    
                    {{-- Hover tooltip --}}
                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-black text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10">
                        {{ $padName }}
                        @if($drumType)
                            <br><span class="text-gray-300">{{ $drumType }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        
        {{-- Selected pad details --}}
        <div x-show="selectedPad !== null" 
             x-transition
             class="mt-4 p-4 bg-white border rounded-lg">
            @foreach($chainsByNote as $midiNote => $chain)
                <div x-show="selectedPad === {{ $midiNote }}" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="font-medium text-lg">{{ $chain['name'] }}</h4>
                        <span class="text-sm text-gray-600">MIDI Note {{ $midiNote }}</span>
                    </div>
                    
                    @if(!empty($chain['devices']))
                        <div class="space-y-2">
                            <h5 class="font-medium text-sm text-gray-700">Device Chain:</h5>
                            <div class="flex flex-wrap gap-2">
                                @foreach($chain['devices'] as $device)
                                    <div class="flex items-center gap-2 px-3 py-1 bg-gray-100 rounded-full text-sm">
                                        @if($device['drum_context']['is_drum_synthesizer'])
                                            <i class="fa-solid fa-waveform text-purple-600" title="Synthesized"></i>
                                        @elseif($device['drum_context']['is_sampler'])
                                            <i class="fa-solid fa-compact-disc text-blue-600" title="Sample-based"></i>
                                        @elseif($device['drum_context']['is_drum_effect'])
                                            <i class="fa-solid fa-sliders text-green-600" title="Effect"></i>
                                        @else
                                            <i class="fa-solid fa-cube text-gray-600"></i>
                                        @endif
                                        <span>{{ $device['name'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    
                    @if(isset($chain['drum_annotations']['velocity_range']))
                        <div class="text-sm text-gray-600">
                            <span class="font-medium">Velocity Range:</span> 
                            {{ $chain['drum_annotations']['velocity_range']['low_vel'] }}-{{ $chain['drum_annotations']['velocity_range']['high_vel'] }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- List View: Traditional chain listing --}}
    <div x-show="viewMode === 'list'" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         class="space-y-2">
        
        @forelse($drumRackData['drum_chains'] ?? [] as $chainIndex => $chain)
            <div class="drum-chain-item bg-gray-50 rounded-lg p-3 hover:bg-gray-100 transition-colors">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-3">
                        {{-- MIDI note indicator --}}
                        @if(isset($chain['drum_annotations']['midi_note']))
                            <div class="w-8 h-8 rounded-full bg-ableton-accent text-white text-xs font-bold flex items-center justify-center">
                                {{ $chain['drum_annotations']['midi_note'] }}
                            </div>
                        @else
                            <div class="w-8 h-8 rounded-full bg-gray-400 text-white text-xs font-bold flex items-center justify-center">
                                {{ $chainIndex + 1 }}
                            </div>
                        @endif
                        
                        <div>
                            <div class="font-medium text-sm">{{ $chain['name'] }}</div>
                            @if(isset($chain['drum_annotations']['drum_type']))
                                <div class="text-xs text-gray-600">{{ $chain['drum_annotations']['drum_type'] }}</div>
                            @endif
                        </div>
                    </div>
                    
                    {{-- Device count and complexity --}}
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <span>{{ count($chain['devices'] ?? []) }} devices</span>
                        @if($chain['is_soloed'] ?? false)
                            <i class="fa-solid fa-volume-high text-yellow-600" title="Soloed"></i>
                        @endif
                    </div>
                </div>
                
                {{-- Device chain visualization --}}
                @if(!empty($chain['devices']))
                    <div class="flex flex-wrap gap-1 ml-11">
                        @foreach($chain['devices'] as $device)
                            <div class="flex items-center gap-1 px-2 py-1 bg-white rounded text-xs border">
                                @if($device['drum_context']['is_drum_synthesizer'])
                                    <i class="fa-solid fa-waveform text-purple-600"></i>
                                @elseif($device['drum_context']['is_sampler'])
                                    <i class="fa-solid fa-compact-disc text-blue-600"></i>
                                @elseif($device['drum_context']['is_drum_effect'])
                                    <i class="fa-solid fa-sliders text-green-600"></i>
                                @else
                                    <i class="fa-solid fa-cube text-gray-600"></i>
                                @endif
                                <span>{{ $device['name'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="ml-11 text-xs text-gray-500 italic">Empty pad</div>
                @endif
            </div>
        @empty
            <div class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-drum text-2xl mb-2 block"></i>
                <p>No drum chains found in this rack</p>
            </div>
        @endforelse
    </div>

    {{-- Performance View: Analysis and insights --}}
    @if(isset($drumRackData['performance_analysis']))
    <div x-show="viewMode === 'performance'" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         class="space-y-4">
        
        {{-- Recommendations --}}
        @if(!empty($drumRackData['performance_analysis']['recommendations']))
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h4 class="font-medium text-blue-900 mb-2 flex items-center gap-2">
                <i class="fa-solid fa-lightbulb"></i>
                Performance Recommendations
            </h4>
            <ul class="space-y-1 text-sm">
                @foreach($drumRackData['performance_analysis']['recommendations'] as $recommendation)
                    <li class="flex items-start gap-2 text-blue-800">
                        <i class="fa-solid fa-arrow-right text-xs mt-1"></i>
                        <span>{{ $recommendation }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
        @endif
        
        {{-- Warnings --}}
        @if(!empty($drumRackData['performance_analysis']['warnings']))
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <h4 class="font-medium text-yellow-900 mb-2 flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Performance Warnings
            </h4>
            <ul class="space-y-1 text-sm">
                @foreach($drumRackData['performance_analysis']['warnings'] as $warning)
                    <li class="flex items-start gap-2 text-yellow-800">
                        <i class="fa-solid fa-exclamation text-xs mt-1"></i>
                        <span>{{ $warning }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
    @endif
</div>

{{-- Styles for drum rack visualizer --}}
<style>
.drum-pad {
    min-height: 60px;
}

.drum-pad-grid .drum-pad:hover {
    transform: scale(1.05) translateZ(0);
}

.drum-chain-item {
    transition: all 0.2s ease;
}

.drum-chain-item:hover {
    transform: translateX(2px);
}

@media (max-width: 640px) {
    .drum-pad-grid .grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .drum-pad {
        min-height: 80px;
    }
}
</style>