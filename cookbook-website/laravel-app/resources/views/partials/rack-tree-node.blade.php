{{-- Recursive partial for rendering a chain node in the rack tree view.
     Variables:
       $chain      - array with keys: name, devices (each device may have its own 'chains')
       $chainIndex - integer index of this chain in its parent
       $level      - integer nesting depth, 0 = top-level chain
--}}
<div class="chain-branch mb-2" x-data="{ expanded: true }" style="margin-left: {{ $level * 16 }}px">
    <div class="flex items-center cursor-pointer hover:bg-gray-50 p-2 rounded"
         @click="expanded = !expanded">
        <svg class="w-4 h-4 mr-2 transition-transform duration-300 ease-in-out"
             :class="expanded ? 'rotate-90' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="font-semibold text-sm">{{ $chain['name'] ?? 'Chain ' . ($chainIndex + 1) }}</span>
        <span class="ml-auto text-xs text-gray-500">{{ count($chain['devices'] ?? []) }} devices</span>
    </div>

    <div x-show="expanded"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="ml-6 mt-1">
        @if(!empty($chain['devices']))
            @foreach($chain['devices'] as $device)
                <div class="device-leaf flex items-center py-1 px-2 text-sm hover:bg-gray-50 rounded">
                    <svg class="w-3 h-3 mr-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <circle cx="10" cy="10" r="3"/>
                    </svg>
                    <span class="text-gray-700">{{ $device['display_name'] ?? $device['name'] ?? $device['standard_name'] ?? 'Unknown Device' }}</span>
                </div>
                @if(!empty($device['chains']))
                    @foreach($device['chains'] as $nestedChainIndex => $nestedChain)
                        @include('partials.rack-tree-node', [
                            'chain'      => $nestedChain,
                            'chainIndex' => $nestedChainIndex,
                            'level'      => $level + 1,
                        ])
                    @endforeach
                @endif
            @endforeach
        @else
            <div class="text-xs text-gray-500 italic px-2">No devices</div>
        @endif
    </div>
</div>
