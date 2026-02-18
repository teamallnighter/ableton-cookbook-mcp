<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Annotate Chains - {{ config('app.name', 'Ableton Cookbook') }}</title>

    <!-- Fonts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/teamallnighter/abletonSans@latest/abletonSans.css">

    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-ZK491B502K"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-ZK491B502K');
    </script>


    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased" style="background-color: #C3C3C3;">
    <!-- Navigation -->
    <nav class="shadow-sm border-b-2" style="background-color: #0D0D0D; border-color: #01CADA;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center">
                        <span class="text-xl font-bold" style="color: #ffdf00;">🎵 Ableton Cookbook</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Progress Indicator -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
        <div class="flex items-center justify-center mb-8">
            <div class="flex items-center space-x-4">
                <!-- Step 1: Upload (Complete) -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-vibrant-green flex items-center justify-center text-black font-bold">
                        ✓
                    </div>
                    <span class="ml-2 text-black font-semibold">Upload</span>
                </div>
                
                <!-- Connector -->
                <div class="w-16 h-1 bg-vibrant-green"></div>
                
                <!-- Step 2: Annotate (Active) -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-vibrant-green flex items-center justify-center text-black font-bold">
                        2
                    </div>
                    <span class="ml-2 text-black font-semibold">Annotate</span>
                </div>
                
                <!-- Connector -->
                <div class="w-16 h-1 bg-gray-300"></div>
                
                <!-- Step 3: Details (Inactive) -->
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold">
                        3
                    </div>
                    <span class="ml-2 text-gray-600">Details</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        <div class="card card-body">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold mb-2 text-black">Customize Your Chains</h1>
                <p class="text-gray-600 mb-4">Give your chains meaningful names to help others understand your rack</p>
                <div class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                    <span class="font-medium">{{ $rack->title }}</span>
                    <span class="mx-2">•</span>
                    <span>{{ ucfirst(str_replace('GroupDevice', ' Rack', $rack->rack_type)) }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('racks.annotate.save', $rack) }}" class="space-y-6">
                @csrf

                @if(!empty($rack->chains))
                    <div class="space-y-4">
                        @php
                            $chainCounter = 0;
                        @endphp
                        
                        @foreach($rack->chains as $chainIndex => $chain)
                            @php
                                // Apply the same flattening logic as in RackShow Livewire component
                                $flattenedChain = $chain;
                                $nestedChains = [];
                                
                                // Check if this chain has nested Audio Effect Racks that should be flattened
                                if (!empty($chain['devices'])) {
                                    $nonRackDevices = [];
                                    foreach ($chain['devices'] as $device) {
                                        if (in_array($device['type'] ?? '', ['AudioEffectGroupDevice', 'InstrumentGroupDevice', 'MidiEffectGroupDevice']) 
                                            && !empty($device['chains'])) {
                                            // This is a nested rack - promote its chains to nested_chains
                                            foreach ($device['chains'] as $nestedChain) {
                                                $nestedChains[] = $nestedChain;
                                            }
                                        } else {
                                            // This is a regular device
                                            $nonRackDevices[] = $device;
                                        }
                                    }
                                    $flattenedChain['devices'] = $nonRackDevices;
                                    $flattenedChain['nested_chains'] = array_merge($chain['nested_chains'] ?? [], $nestedChains);
                                }
                            @endphp
                            
                            <!-- Main Chain -->
                            <div class="border-2 border-black rounded-lg overflow-hidden">
                                <div class="bg-white p-6">
                                    <div class="flex items-start gap-6">
                                        <!-- Chain Icon -->
                                        <div class="flex-shrink-0">
                                            <div class="w-12 h-12 rounded-lg bg-vibrant-purple flex items-center justify-center">
                                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                                                </svg>
                                            </div>
                                        </div>

                                        <div class="flex-1">
                                            <!-- Chain Name Input -->
                                            <div class="mb-4">
                                                <label for="chain_{{ $chainCounter }}_name" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Chain {{ $chainIndex + 1 }} Name (Optional)
                                                </label>
                                                <input type="text" 
                                                       id="chain_{{ $chainCounter }}_name"
                                                       name="chain_annotations[{{ $chainIndex }}][custom_name]" 
                                                       value="{{ $rack->chain_annotations[$chainIndex]['custom_name'] ?? '' }}"
                                                       placeholder="e.g., Low End Processing, Vocal Chain, Creative Effects..."
                                                       maxlength="100"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-vibrant-green focus:border-vibrant-green">
                                                <div class="text-xs text-gray-500 mt-1">
                                                    <span id="char_count_{{ $chainCounter }}">0</span>/100 characters
                                                </div>
                                            </div>

                                            <!-- Device List -->
                                            <div class="bg-gray-50 rounded-lg p-4">
                                                <h4 class="font-medium text-black mb-3">
                                                    Devices in this chain ({{ count($flattenedChain['devices']) }}):
                                                </h4>
                                                
                                                @if(!empty($flattenedChain['devices']))
                                                    <div class="flex flex-wrap gap-2">
                                                        @foreach($flattenedChain['devices'] as $device)
                                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white border border-gray-200">
                                                                <span class="w-2 h-2 rounded-full bg-vibrant-green mr-2"></span>
                                                                {{ $device['friendly_name'] ?? $device['type'] ?? 'Unknown Device' }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <p class="text-gray-500 text-sm italic">No devices in this chain</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            @php $chainCounter++; @endphp

                            <!-- Nested Chains (if any) -->
                            @if(!empty($flattenedChain['nested_chains']))
                                @foreach($flattenedChain['nested_chains'] as $nestedChainIndex => $nestedChain)
                                    <div class="border-2 border-vibrant-purple rounded-lg overflow-hidden ml-8">
                                        <div class="bg-purple-50 p-6">
                                            <div class="flex items-start gap-6">
                                                <!-- Nested Chain Icon -->
                                                <div class="flex-shrink-0">
                                                    <div class="w-10 h-10 rounded-lg bg-vibrant-purple/80 flex items-center justify-center">
                                                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                                                        </svg>
                                                    </div>
                                                </div>

                                                <div class="flex-1">
                                                    <!-- Nested Chain Name Input -->
                                                    <div class="mb-4">
                                                        <label for="chain_{{ $chainCounter }}_name" class="block text-sm font-medium text-purple-700 mb-2">
                                                            Chain {{ $chainIndex + 1 }}.{{ $nestedChainIndex + 1 }} Name (Optional)
                                                            <span class="text-xs text-purple-600 font-normal">— Nested Chain</span>
                                                        </label>
                                                        <input type="text" 
                                                               id="chain_{{ $chainCounter }}_name"
                                                               name="chain_annotations[{{ $chainIndex }}_{{ $nestedChainIndex }}][custom_name]" 
                                                               value="{{ $rack->chain_annotations[$chainIndex . '_' . $nestedChainIndex]['custom_name'] ?? '' }}"
                                                               placeholder="e.g., Parallel Compression, High Pass Section..."
                                                               maxlength="100"
                                                               class="w-full px-3 py-2 border border-purple-300 rounded-lg focus:ring-2 focus:ring-vibrant-purple focus:border-vibrant-purple bg-white">
                                                        <div class="text-xs text-purple-500 mt-1">
                                                            <span id="char_count_{{ $chainCounter }}">0</span>/100 characters
                                                        </div>
                                                    </div>

                                                    <!-- Nested Chain Device List -->
                                                    <div class="bg-white rounded-lg p-4 border border-purple-200">
                                                        <h5 class="font-medium text-purple-800 mb-3">
                                                            Devices in nested chain ({{ count($nestedChain['devices'] ?? []) }}):
                                                        </h5>
                                                        
                                                        @if(!empty($nestedChain['devices']))
                                                            <div class="flex flex-wrap gap-2">
                                                                @foreach($nestedChain['devices'] as $device)
                                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                                        <span class="w-1.5 h-1.5 rounded-full bg-vibrant-purple mr-1.5"></span>
                                                                        {{ $device['friendly_name'] ?? $device['type'] ?? 'Unknown Device' }}
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                        @else
                                                            <p class="text-purple-500 text-sm italic">No devices in this nested chain</p>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    @php $chainCounter++; @endphp
                                @endforeach
                            @endif
                        @endforeach
                    </div>

                    <!-- Quick Fill Suggestions -->
                    <div class="bg-blue-50 rounded-lg p-6">
                        <h3 class="font-medium text-blue-900 mb-3">💡 Need inspiration? Try these naming patterns:</h3>
                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                            <div class="bg-white rounded p-3 border border-blue-200">
                                <div class="font-medium text-blue-800 mb-1">By Function</div>
                                <div class="text-blue-600 text-xs space-y-1">
                                    <div>• Low End Processing</div>
                                    <div>• Mid Range Shaping</div>
                                    <div>• High End Sparkle</div>
                                </div>
                            </div>
                            <div class="bg-white rounded p-3 border border-blue-200">
                                <div class="font-medium text-blue-800 mb-1">By Effect Type</div>
                                <div class="text-blue-600 text-xs space-y-1">
                                    <div>• Compression & EQ</div>
                                    <div>• Modulation FX</div>
                                    <div>• Creative Effects</div>
                                </div>
                            </div>
                            <div class="bg-white rounded p-3 border border-blue-200">
                                <div class="font-medium text-blue-800 mb-1">For Nested Chains</div>
                                <div class="text-blue-600 text-xs space-y-1">
                                    <div>• Parallel Compression</div>
                                    <div>• Wet/Dry Split</div>
                                    <div>• Frequency Split</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-12">
                        <div class="text-gray-500 text-lg">No chains found in this rack</div>
                    </div>
                @endif

                <!-- Action Buttons -->
                <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                    <a href="{{ route('racks.upload') }}" 
                       class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        ← Start Over
                    </a>

                    <div class="flex gap-3">
                        <button type="submit" 
                                name="skip" 
                                value="1"
                                class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Skip This Step
                        </button>
                        
                        <button type="submit" 
                                class="px-8 py-3 bg-vibrant-green text-black font-semibold rounded-lg hover:opacity-90 transition-opacity">
                            Next: Add Details →
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Character counter for chain names
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input[name*="custom_name"]');
            
            inputs.forEach((input, index) => {
                const counter = document.getElementById(`char_count_${index}`);
                
                function updateCounter() {
                    const count = input.value.length;
                    if (counter) {
                        counter.textContent = count;
                        counter.className = count > 80 ? 'text-orange-500' : (input.name.includes('_') ? 'text-purple-500' : 'text-gray-500');
                    }
                }
                
                input.addEventListener('input', updateCounter);
                updateCounter(); // Initial count
            });
        });
    </script>
</body>
</html>
