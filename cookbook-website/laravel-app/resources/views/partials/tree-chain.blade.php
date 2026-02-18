{{-- Tree Chain Partial - Recursively renders chains and their devices --}}
@php
    $nodeId = 'chain_' . $chainIndex . '_level_' . $level . '_' . uniqid();
    $hasDevices = !empty($chain['devices']);
    $hasNestedChains = !empty($chain['nested_chains']);
    $hasChildren = $hasDevices || $hasNestedChains;
@endphp

<div class="tree-node" data-node-id="{{ $nodeId }}" role="treeitem" @if($hasChildren) aria-expanded="false" @endif aria-level="{{ $level }}" @if($hasChildren) aria-describedby="{{ $nodeId }}-desc" @endif>
    <!-- Chain Header -->
    <div class="tree-item chain-node" data-node-id="{{ $nodeId }}" tabindex="0" role="button" @if($hasChildren) aria-expanded="false" @endif>
        <div class="flex items-center gap-2 w-full">
            @if($hasChildren)
                <button 
                    class="toggle-btn" 
                    @click="toggleNode('{{ $nodeId }}', $event)" 
                    @touchstart="handleTouchStart($event, '{{ $nodeId }}')"
                    @touchend="handleTouchEnd($event, '{{ $nodeId }}')"
                    title="Toggle chain {{ $chainIndex + 1 }}"
                    aria-label="{{ $hasChildren ? 'Expand' : 'Collapse' }} chain {{ $chainIndex + 1 }}"
                >
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            @else
                <div class="toggle-btn"></div>
            @endif

            <div class="device-icon">
                <div class="w-2 h-2 rounded-full bg-vibrant-purple"></div>
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-black truncate" id="{{ $nodeId }}-label">
                        @if(isset($rack->chain_annotations[$chainIndex]['custom_name']) && !empty($rack->chain_annotations[$chainIndex]['custom_name']))
                            {{ $rack->chain_annotations[$chainIndex]['custom_name'] }}
                        @else
                            Chain {{ $chainIndex + 1 }}
                        @endif
                    </span>
                    
                    @if($hasDevices || $hasNestedChains)
                        <span class="text-xs text-gray-500" id="{{ $nodeId }}-desc">
                            ({{ count($chain['devices'] ?? []) + count($chain['nested_chains'] ?? []) }} 
                            {{ Str::plural('item', count($chain['devices'] ?? []) + count($chain['nested_chains'] ?? [])) }})
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($hasChildren)
        <!-- Chain Children -->
        <div class="tree-children" style="display: none;" role="group" aria-labelledby="{{ $nodeId }}-label">
            {{-- Regular Devices --}}
            @if($hasDevices)
                @foreach($chain['devices'] as $deviceIndex => $device)
                    @include('partials.tree-device', [
                        'device' => $device, 
                        'deviceIndex' => $deviceIndex,
                        'chainIndex' => $chainIndex,
                        'level' => $level + 1
                    ])
                @endforeach
            @endif

            {{-- Nested Chains (from rack devices) --}}
            @if($hasNestedChains)
                @foreach($chain['nested_chains'] as $nestedChainIndex => $nestedChain)
                    @include('partials.tree-chain', [
                        'chain' => $nestedChain,
                        'chainIndex' => $nestedChainIndex,
                        'level' => $level + 1
                    ])
                @endforeach
            @endif
        </div>
    @endif
</div>