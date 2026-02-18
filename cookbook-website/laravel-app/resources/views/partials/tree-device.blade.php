{{-- Tree Device Partial - Renders individual devices with nested chains if present --}}
@php
    $nodeId = 'device_' . $chainIndex . '_' . $deviceIndex . '_level_' . $level . '_' . uniqid();
    $deviceName = $device['name'] ?? 'Unknown Device';
    $devicePreset = $device['preset'] ?? null;
    $deviceType = $device['type'] ?? 'Unknown';
    $hasNestedChains = !empty($device['chains']);
@endphp

<div class="tree-node" data-node-id="{{ $nodeId }}" role="treeitem" @if($hasNestedChains) aria-expanded="false" @endif aria-level="{{ $level }}" aria-label="{{ $deviceName }}{{ $hasNestedChains ? ' with ' . count($device['chains']) . ' chains' : '' }}">
    <!-- Device Header -->
    <div class="tree-item device-node" data-node-id="{{ $nodeId }}" tabindex="0" role="button" @if($hasNestedChains) aria-expanded="false" @endif>
        <div class="flex items-center gap-2 w-full">
            @if($hasNestedChains)
                <button 
                    class="toggle-btn" 
                    @click="toggleNode('{{ $nodeId }}', $event)" 
                    @touchstart="handleTouchStart($event, '{{ $nodeId }}')"
                    @touchend="handleTouchEnd($event, '{{ $nodeId }}')"
                    title="{{ $hasNestedChains ? 'Expand' : 'Collapse' }} {{ $deviceName }} chains"
                    aria-label="{{ $hasNestedChains ? 'Expand' : 'Collapse' }} {{ $deviceName }} chains"
                >
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            @else
                <div class="toggle-btn"></div>
            @endif

            <!-- Device Type Icon -->
            <div class="device-icon">
                @if(str_contains($deviceType, 'Instrument') || str_contains(strtolower($deviceName), 'drum') || str_contains(strtolower($deviceName), 'synth'))
                    <i class="fa-duotone fa-thin fa-piano-keyboard text-blue-600" title="Instrument"></i>
                @elseif(str_contains($deviceType, 'MidiEffect') || str_contains(strtolower($deviceName), 'arp') || str_contains(strtolower($deviceName), 'chord'))
                    <i class="fa-thin fa-file-midi text-green-600" title="MIDI Effect"></i>
                @elseif(str_contains($deviceType, 'AudioEffect') || str_contains(strtolower($deviceName), 'reverb') || str_contains(strtolower($deviceName), 'delay') || str_contains(strtolower($deviceName), 'eq') || str_contains(strtolower($deviceName), 'comp'))
                    <i class="fa-thin fa-dial-med text-purple-600" title="Audio Effect"></i>
                @elseif(str_contains($deviceType, 'GroupDevice') || str_contains(strtolower($deviceName), 'rack'))
                    <i class="fa-thin fa-cube text-orange-600" title="Rack Device"></i>
                @else
                    <i class="fa-thin fa-circle text-gray-500" title="Device"></i>
                @endif
            </div>

            <!-- Device Info -->
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-gray-800 truncate" title="{{ $deviceName }}" id="{{ $nodeId }}-label">
                        {{ $deviceName }}
                    </span>
                    
                    @if($hasNestedChains)
                        <span class="text-xs text-gray-500" id="{{ $nodeId }}-desc">
                            ({{ count($device['chains']) }} {{ Str::plural('chain', count($device['chains'])) }})
                        </span>
                    @endif
                </div>
                
                @if($devicePreset && $devicePreset !== $deviceName)
                    <div class="device-preset truncate" title="Preset: {{ $devicePreset }}" aria-label="Preset: {{ $devicePreset }}">
                        {{ $devicePreset }}
                    </div>
                @endif
            </div>

            <!-- Device Type Badge -->
            <div class="text-xs text-gray-400 shrink-0" title="Type: {{ $deviceType }}" aria-label="Device type: {{ $deviceType }}">
                @if(str_contains($deviceType, 'Instrument'))
                    INST
                @elseif(str_contains($deviceType, 'MidiEffect'))
                    MIDI
                @elseif(str_contains($deviceType, 'AudioEffect'))
                    FX
                @elseif(str_contains($deviceType, 'GroupDevice'))
                    RACK
                @else
                    DEV
                @endif
            </div>
        </div>
    </div>

    @if($hasNestedChains)
        <!-- Device's Nested Chains -->
        <div class="tree-children" style="display: none;" role="group" aria-labelledby="{{ $nodeId }}-label" @if($hasNestedChains) aria-describedby="{{ $nodeId }}-desc" @endif>
            @foreach($device['chains'] as $nestedChainIndex => $nestedChain)
                @include('partials.tree-chain', [
                    'chain' => $nestedChain,
                    'chainIndex' => $nestedChainIndex,
                    'level' => $level + 1
                ])
            @endforeach
        </div>
    @endif
</div>