<?php

namespace App\Livewire;

use App\Models\Rack;
use App\Models\RackRating;
use App\Models\RackFavorite;
use App\Models\RackReport;
use App\Jobs\IncrementRackViewsJob;
use App\Services\DrumRackAnalyzerService;
use App\Services\RackProcessingService;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class RackShow extends Component
{
    public Rack $rack;
    public $rackData;
    public $drumRackData;
    public $userRating = 0;
    public $hoveredStar = 0;
    public $isFavorited = false;
    
    // D2 diagram properties
    public $currentDiagramStyle = 'sketch';
    public $availableStyles = ['sketch', 'technical', 'minimal', 'neon'];
    public $diagramCache = [];
    
    // Report modal state
    public $showReportModal = false;
    public $reportIssueType = '';
    public $reportDescription = '';

    public function mount(Rack $rack)
    {
        $this->rack = $rack->load('user');
        
        // Increment view count asynchronously to avoid blocking response
        IncrementRackViewsJob::dispatch($this->rack->id);
        
        $this->parseRackStructure();
        
        if (Auth::check()) {
            // Load user's current rating for this rack
            $userRating = RackRating::where('rack_id', $this->rack->id)
                ->where('user_id', Auth::id())
                ->first();
            
            if ($userRating) {
                $this->userRating = $userRating->rating;
            }
            
            // Check if user has favorited this rack
            $this->isFavorited = RackFavorite::where('rack_id', $this->rack->id)
                ->where('user_id', Auth::id())
                ->exists();
        }
    }

    public function rateRack($rating)
    {
        if (!Auth::check()) {
            $this->dispatch('show-login-modal');
            return;
        }

        $this->userRating = $rating;

        // Create or update the rating
        RackRating::updateOrCreate(
            [
                'rack_id' => $this->rack->id,
                'user_id' => Auth::id(),
            ],
            [
                'rating' => $rating,
            ]
        );

        // Update the rack's cached rating statistics
        $this->updateRackRatingStats();
        
        $this->dispatch('rating-updated');
    }

    public function setHoveredStar($star)
    {
        $this->hoveredStar = $star;
    }

    public function clearHoveredStar()
    {
        $this->hoveredStar = 0;
    }

    private function updateRackRatingStats()
    {
        $ratings = RackRating::where('rack_id', $this->rack->id)->get();
        
        $this->rack->update([
            'average_rating' => $ratings->avg('rating'),
            'ratings_count' => $ratings->count(),
        ]);
        
        // Clear related caches
        Cache::forget("rack_structure_{$this->rack->id}");
    }

    public function toggleFavorite()
    {
        if (!Auth::check()) {
            $this->dispatch('show-login-modal');
            return;
        }

        $favorite = RackFavorite::where('rack_id', $this->rack->id)
            ->where('user_id', Auth::id())
            ->first();

        if ($favorite) {
            $favorite->delete();
            $this->isFavorited = false;
            $this->dispatch('favorite-removed');
        } else {
            RackFavorite::create([
                'rack_id' => $this->rack->id,
                'user_id' => Auth::id(),
            ]);
            $this->isFavorited = true;
            $this->dispatch('favorite-added');
        }
    }

    public function downloadRack()
    {
        if (!Auth::check()) {
            $this->dispatch('show-login-modal');
            return;
        }

        // Increment download count
        $this->rack->increment('downloads_count');
        
        // Return download response
        return response()->download(
            Storage::disk('private')->path($this->rack->file_path),
            $this->rack->title . '.adg'
        );
    }

    public function openReportModal()
    {
        if (!Auth::check()) {
            $this->dispatch('show-login-modal');
            return;
        }
        
        $this->showReportModal = true;
        $this->reportIssueType = '';
        $this->reportDescription = '';
    }

    public function closeReportModal()
    {
        $this->showReportModal = false;
        $this->reportIssueType = '';
        $this->reportDescription = '';
    }

    public function submitReport()
    {
        $this->validate([
            'reportIssueType' => 'required|string',
            'reportDescription' => 'required|string|min:10|max:500',
        ]);

        RackReport::create([
            'rack_id' => $this->rack->id,
            'reporter_id' => Auth::id(),
            'issue_type' => $this->reportIssueType,
            'description' => $this->reportDescription,
            'status' => 'pending',
        ]);

        $this->closeReportModal();
        $this->dispatch('report-submitted');
    }

    public function shareRack()
    {
        $url = url("/racks/{$this->rack->id}");
        
        $this->dispatch('copy-to-clipboard', [
            'text' => $url,
            'message' => 'Rack URL copied to clipboard!'
        ]);
    }

    private function parseRackStructure()
    {
        // Cache rack structure data to avoid repeated heavy operations
        $this->rackData = Cache::remember(
            "rack_structure_{$this->rack->id}", 
            3600, // Cache for 1 hour
            function() {
                if ($this->rack->chains) {
                    // Use stored chain data if available
                    $chains = is_array($this->rack->chains) 
                        ? $this->rack->chains 
                        : json_decode($this->rack->chains, true);
                    
                    // Apply recursive flattening to all levels
                    $flattenedChains = $this->flattenAllRackStructures($chains);
                    
                    return [
                        'chains' => $flattenedChains,
                        'type' => $this->rack->rack_type
                    ];
                } elseif ($this->rack->devices) {
                    // Fall back to device data grouped into a single chain
                    $devices = is_array($this->rack->devices)
                        ? $this->rack->devices
                        : json_decode($this->rack->devices, true);
                        
                    return [
                        'chains' => $this->groupDevicesIntoChains($devices),
                        'type' => $this->rack->rack_type
                    ];
                } else {
                    // Try to re-analyze from file
                    return $this->reAnalyzeRackStructure();
                }
            }
        );
    }

    /**
     * Recursively flatten Audio Effect Rack structures at ALL levels
     */
    private function flattenAllRackStructures($chains)
    {
        // First, flatten the main level wrapper
        $chains = $this->flattenMainWrapperLevel($chains);
        
        // Then, recursively process each chain to flatten nested structures
        foreach ($chains as &$chain) {
            $chain = $this->processChainRecursively($chain);
        }
        
        return $chains;
    }

    /**
     * Flatten main wrapper level (top-level fix)
     */
    private function flattenMainWrapperLevel($chains)
    {
        // Check if we have the main wrapper pattern:
        // - Single chain containing single Audio Effect Rack device with chains
        if (count($chains) === 1 && 
            isset($chains[0]['devices']) && 
            count($chains[0]['devices']) === 1) {
            
            $device = $chains[0]['devices'][0];
            
            // If the single device is an Audio Effect Rack with chains, flatten it
            if (isset($device['type']) && 
                $device['type'] === 'AudioEffectGroupDevice' &&
                isset($device['chains']) && 
                !empty($device['chains'])) {
                
                // Return the device's chains as the main rack chains
                return $device['chains'];
            }
        }
        
        return $chains;
    }

    /**
     * Process a single chain and flatten any nested Audio Effect Racks
     */
    private function processChainRecursively($chain)
    {
        if (!isset($chain['devices']) || empty($chain['devices'])) {
            return $chain;
        }

        $processedDevices = [];
        $hasNestedChains = false;
        $nestedChains = [];

        foreach ($chain['devices'] as $device) {
            if (isset($device['type']) && 
                $device['type'] === 'AudioEffectGroupDevice' &&
                isset($device['chains']) && 
                !empty($device['chains'])) {
                
                // This device is an Audio Effect Rack with chains
                // Instead of showing it as a device, promote its chains
                $hasNestedChains = true;
                
                foreach ($device['chains'] as $nestedChain) {
                    // Recursively process nested chains
                    $nestedChains[] = $this->processChainRecursively($nestedChain);
                }
            } else {
                // Regular device - keep it
                $processedDevices[] = $this->improveDeviceInfo($device);
            }
        }

        // If we found nested chains, replace devices with nested chains
        if ($hasNestedChains) {
            $chain['nested_chains'] = $nestedChains;
            $chain['devices'] = $processedDevices; // Keep any regular devices too
        } else {
            $chain['devices'] = $processedDevices;
        }

        return $chain;
    }

    /**
     * Improve device information display
     */
    private function improveDeviceInfo($device)
    {
        // Map internal names to user-friendly names
        $deviceNameMap = [
            'StereoGain' => 'Utility',
            'Eq8' => 'EQ Eight',
            'AudioEffectGroupDevice' => 'Audio Effect Rack',
        ];
        
        // Use type-based mapping first, then standard_name as fallback
        if (isset($device['type']) && isset($deviceNameMap[$device['type']])) {
            $device['display_name'] = $deviceNameMap[$device['type']];
        } elseif (isset($device['standard_name'])) {
            $device['display_name'] = $device['standard_name'];
        } else {
            $device['display_name'] = $device['name'] ?? 'Unknown Device';
        }
        
        return $device;
    }

    private function groupDevicesIntoChains($devices)
    {
        // Group devices into a single chain for display
        return [
            [
                'name' => 'Chain 1',
                'devices' => $devices,
                'is_soloed' => false,
                'annotations' => ['tags' => [], 'purpose' => null, 'key_range' => null, 'description' => null, 'velocity_range' => null],
                'chain_index' => 0,
            ]
        ];
    }

    private function reAnalyzeRackStructure()
    {
        if (Storage::disk('private')->exists($this->rack->file_path)) {
            try {
                $filePath = Storage::disk('private')->path($this->rack->file_path);
                $xml = \App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer::decompressAndParseAbletonFile($filePath);
                $analysis = \App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer::parseChainsAndDevices($xml, $this->rack->title, false);
                
                if ($analysis && isset($analysis['chains'])) {
                    // Apply recursive flattening to analyzed data
                    $flattenedChains = $this->flattenAllRackStructures($analysis['chains']);
                    
                    $rackData = [
                        'chains' => $flattenedChains,
                        'type' => $this->rack->rack_type
                    ];
                    
                    // Update the rack with analyzed data for future use
                    $this->rack->update([
                        'chains' => json_encode($flattenedChains),
                        'devices' => json_encode($this->flattenDevices($flattenedChains))
                    ]);
                    
                    return $rackData;
                } else {
                    return [
                        'chains' => [],
                        'type' => $this->rack->rack_type
                    ];
                }
            } catch (\Exception $e) {
                return [
                    'chains' => [],
                    'type' => $this->rack->rack_type
                ];
            }
        } else {
            return [
                'chains' => [],
                'type' => $this->rack->rack_type
            ];
        }
    }

    private function flattenDevices($chains)
    {
        $devices = [];
        foreach ($chains as $chain) {
            if (isset($chain['devices'])) {
                $devices = array_merge($devices, $chain['devices']);
            }
        }
        return $devices;
    }


    /**
     * Get drum rack specific data for visualization
     */
    public function getDrumRackData()
    {
        if (!$this->isDrumRack()) {
            return null;
        }

        // Cache drum rack analysis to avoid repeated heavy operations
        if (!$this->drumRackData) {
            $this->drumRackData = Cache::remember(
                "drum_rack_data_{$this->rack->id}",
                3600, // Cache for 1 hour
                function() {
                    try {
                        if (Storage::disk('private')->exists($this->rack->file_path)) {
                            $drumAnalyzer = app(DrumRackAnalyzerService::class);
                            $filePath = Storage::disk('private')->path($this->rack->file_path);
                            
                            $result = $drumAnalyzer->analyzeDrumRack($filePath, [
                                'include_performance' => true,
                                'verbose' => false
                            ]);

                            if ($result['success']) {
                                return $result['data'];
                            }
                        }

                        // Fallback: try to convert general rack data to drum format
                        return $this->convertGeneralRackToDrumFormat();

                    } catch (\Exception $e) {
                        // Log error but don't break the UI
                        \Log::warning("Failed to analyze drum rack {$this->rack->id}: " . $e->getMessage());
                        return $this->convertGeneralRackToDrumFormat();
                    }
                }
            );
        }

        return $this->drumRackData;
    }

    /**
     * Convert general rack data to drum rack format for visualization
     */
    private function convertGeneralRackToDrumFormat()
    {
        $rackData = $this->rackData ?? ['chains' => []];
        
        return [
            'drum_rack_name' => $this->rack->title,
            'rack_type' => 'drum_rack',
            'ableton_version' => $this->rack->ableton_version,
            'drum_chains' => $this->convertChainsTodrumChains($rackData['chains'] ?? []),
            'drum_statistics' => $this->calculateDrumStatistics($rackData['chains'] ?? []),
            'macro_controls' => $this->rack->macro_controls ?? [],
            'performance_analysis' => null, // Not available without proper analysis
            'parsing_warnings' => [],
            'parsing_errors' => []
        ];
    }

    /**
     * Convert general chains to drum chain format
     */
    private function convertChainsTodrumChains($chains)
    {
        $drumChains = [];
        
        foreach ($chains as $index => $chain) {
            $drumChains[] = [
                'name' => $chain['name'] ?? "Pad " . ($index + 1),
                'devices' => array_map(function($device) {
                    return [
                        'type' => $device['type'] ?? 'Unknown',
                        'name' => $device['display_name'] ?? $device['name'] ?? 'Unknown Device',
                        'standard_name' => $device['standard_name'] ?? '',
                        'is_on' => $device['is_on'] ?? true,
                        'drum_context' => [
                            'is_drum_synthesizer' => false,
                            'is_sampler' => in_array($device['type'] ?? '', ['Sampler', 'Simpler', 'Impulse']),
                            'is_drum_effect' => false
                        ]
                    ];
                }, $chain['devices'] ?? []),
                'is_soloed' => $chain['is_soloed'] ?? false,
                'chain_index' => $index,
                'drum_annotations' => [
                    'description' => null,
                    'drum_type' => null,
                    'key_range' => null,
                    'velocity_range' => null,
                    'midi_note' => 36 + $index, // Default MIDI mapping starting from C1
                    'pad_name' => $chain['name'] ?? "Pad " . ($index + 1),
                    'tags' => []
                ]
            ];
        }
        
        return $drumChains;
    }

    /**
     * Calculate basic drum statistics from chain data
     */
    private function calculateDrumStatistics($chains)
    {
        $totalPads = count($chains);
        $activePads = 0;
        $chainedPads = 0;
        $sampleBasedPads = 0;
        
        foreach ($chains as $chain) {
            $devices = $chain['devices'] ?? [];
            if (!empty($devices)) {
                $activePads++;
                if (count($devices) > 1) {
                    $chainedPads++;
                }
                
                foreach ($devices as $device) {
                    if (in_array($device['type'] ?? '', ['Sampler', 'Simpler', 'Impulse'])) {
                        $sampleBasedPads++;
                        break;
                    }
                }
            }
        }
        
        return [
            'total_pads' => $totalPads,
            'active_pads' => $activePads,
            'chained_pads' => $chainedPads,
            'sample_based_pads' => $sampleBasedPads,
            'synthesized_pads' => $activePads - $sampleBasedPads
        ];
    }

    /**
     * Switch D2 diagram style
     */
    public function switchDiagramStyle($style)
    {
        if (in_array($style, $this->availableStyles)) {
            $this->currentDiagramStyle = $style;
            // Clear cache to force regeneration with new style
            $this->diagramCache = [];
        }
    }
    
    /**
     * Get D2 diagram for current rack
     */
    public function getDiagramSvg()
    {
        // Check cache first
        if (isset($this->diagramCache[$this->currentDiagramStyle])) {
            return $this->diagramCache[$this->currentDiagramStyle];
        }
        
        try {
            $rackProcessor = app(RackProcessingService::class);
            $diagram = $rackProcessor->getRackDiagram($this->rack, $this->currentDiagramStyle, 'svg');
            
            // Cache the result
            $this->diagramCache[$this->currentDiagramStyle] = $diagram;
            
            return $diagram;
        } catch (\Exception $e) {
            \Log::error("Failed to get D2 diagram for rack {$this->rack->uuid}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get ASCII diagram for sharing
     */
    public function getAsciiDiagram()
    {
        try {
            $rackProcessor = app(RackProcessingService::class);
            return $rackProcessor->getRackAsciiDiagram($this->rack);
        } catch (\Exception $e) {
            \Log::error("Failed to get ASCII diagram for rack {$this->rack->uuid}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Copy ASCII diagram to clipboard (JavaScript will handle this)
     */
    public function copyAsciiDiagram()
    {
        $this->dispatch('copy-to-clipboard', ['text' => $this->getAsciiDiagram()]);
    }
    
    /**
     * Check if rack should use drum rack visualization
     */
    public function isDrumRack(): bool
    {
        return $this->rack->rack_type === 'drum_rack' || 
               $this->rack->category === 'drums' ||
               ($this->drumRackData && count($this->drumRackData) > 0);
    }

    public function render()
    {
        return view('livewire.rack-show');
    }
}
