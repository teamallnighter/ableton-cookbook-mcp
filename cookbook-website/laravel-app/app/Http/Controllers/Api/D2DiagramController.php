<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rack;
use App\Services\D2DiagramService;
use App\Services\D2TemplateEngine;
use App\Services\D2StyleThemes;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="D2 Diagrams",
 *     description="Generate beautiful D2 diagrams from Ableton rack data"
 * )
 */
class D2DiagramController extends Controller
{
    private D2DiagramService $diagramService;
    private D2TemplateEngine $templateEngine;
    private D2StyleThemes $styleThemes;

    public function __construct(
        D2DiagramService $diagramService,
        D2TemplateEngine $templateEngine,
        D2StyleThemes $styleThemes
    ) {
        $this->diagramService = $diagramService;
        $this->templateEngine = $templateEngine;
        $this->styleThemes = $styleThemes;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/racks/{rack}/diagram",
     *     summary="Generate D2 diagram for a rack",
     *     description="Generate a beautiful D2 diagram from rack analysis data",
     *     tags={"D2 Diagrams"},
     *     @OA\Parameter(
     *         name="rack",
     *         in="path",
     *         required=true,
     *         description="Rack UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="style",
     *         in="query",
     *         description="Visual style theme",
     *         @OA\Schema(
     *             type="string",
     *             enum={"sketch", "sketch_dark", "technical", "technical_dark", "minimal", "ascii", "neon", "ableton"},
     *             default="sketch"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Output format",
     *         @OA\Schema(
     *             type="string",
     *             enum={"d2", "svg", "png", "pdf"},
     *             default="d2"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="cached",
     *         in="query",
     *         description="Use cached diagram if available",
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="D2 diagram generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="diagram", type="string", description="D2 diagram syntax"),
     *             @OA\Property(property="metadata", type="object",
     *                 @OA\Property(property="rack_name", type="string"),
     *                 @OA\Property(property="rack_type", type="string"),
     *                 @OA\Property(property="theme", type="string"),
     *                 @OA\Property(property="generated_at", type="string", format="date-time"),
     *                 @OA\Property(property="cache_key", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Rack not found"),
     *     @OA\Response(response=422, description="Invalid parameters")
     * )
     */
    public function generateRackDiagram(Request $request, string $rackUuid): JsonResponse
    {
        $validator = Validator::make(array_merge($request->all(), ['rack' => $rackUuid]), [
            'rack' => 'required|uuid|exists:racks,uuid',
            'style' => 'sometimes|string|in:sketch,sketch_dark,technical,technical_dark,minimal,ascii,neon,ableton',
            'format' => 'sometimes|string|in:d2,svg,png,pdf',
            'cached' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $rack = Rack::where('uuid', $rackUuid)->firstOrFail();
        $style = $request->get('style', 'sketch');
        $format = $request->get('format', 'd2');
        $useCached = $request->get('cached', true);

        // Generate cache key
        $cacheKey = "d2_diagram:" . md5($rackUuid . $style . $format . $rack->updated_at);

        // Try to get cached diagram
        if ($useCached) {
            $cachedDiagram = Cache::get($cacheKey);
            if ($cachedDiagram) {
                return response()->json([
                    'success' => true,
                    'diagram' => $cachedDiagram['diagram'],
                    'metadata' => array_merge($cachedDiagram['metadata'], [
                        'cached' => true,
                        'cache_key' => $cacheKey
                    ])
                ]);
            }
        }

        try {
            // Prepare rack data for diagram generation
            $rackData = $this->prepareRackData($rack);

            // Generate D2 diagram
            $diagram = $this->diagramService->generateRackDiagram($rackData, $style);

            $metadata = [
                'rack_name' => $rack->title,
                'rack_type' => $rack->rack_type,
                'theme' => $style,
                'generated_at' => now()->toISOString(),
                'cache_key' => $cacheKey,
                'cached' => false
            ];

            // Cache the result
            $diagramData = ['diagram' => $diagram, 'metadata' => $metadata];
            Cache::put($cacheKey, $diagramData, now()->addHour());

            // If format is not D2, render to requested format
            if ($format !== 'd2') {
                $renderedDiagram = $this->renderDiagram($diagram, $format);
                return response()->json([
                    'success' => true,
                    'diagram' => $renderedDiagram,
                    'metadata' => $metadata
                ]);
            }

            return response()->json([
                'success' => true,
                'diagram' => $diagram,
                'metadata' => $metadata
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate diagram: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/diagrams/compare",
     *     summary="Generate comparison diagram between two racks",
     *     description="Create a side-by-side comparison diagram of two racks",
     *     tags={"D2 Diagrams"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rack_a", "rack_b"},
     *             @OA\Property(property="rack_a", type="string", format="uuid", description="First rack UUID"),
     *             @OA\Property(property="rack_b", type="string", format="uuid", description="Second rack UUID"),
     *             @OA\Property(property="style", type="string", enum={"sketch", "technical", "minimal"}, default="sketch")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Comparison diagram generated successfully"
     *     )
     * )
     */
    public function generateComparisonDiagram(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rack_a' => 'required|uuid|exists:racks,uuid',
            'rack_b' => 'required|uuid|exists:racks,uuid',
            'style' => 'sometimes|string|in:sketch,technical,minimal'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $rackA = Rack::where('uuid', $request->rack_a)->firstOrFail();
            $rackB = Rack::where('uuid', $request->rack_b)->firstOrFail();

            $rackDataA = $this->prepareRackData($rackA);
            $rackDataB = $this->prepareRackData($rackB);

            $diagram = $this->diagramService->generateComparisonDiagram($rackDataA, $rackDataB);

            return response()->json([
                'success' => true,
                'diagram' => $diagram,
                'metadata' => [
                    'rack_a' => $rackA->title,
                    'rack_b' => $rackB->title,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate comparison: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/diagrams/database-schema",
     *     summary="Generate database schema diagram",
     *     description="Generate D2 diagram of Laravel model relationships",
     *     tags={"D2 Diagrams"},
     *     @OA\Parameter(
     *         name="style",
     *         in="query",
     *         description="Visual style",
     *         @OA\Schema(type="string", enum={"technical", "minimal"}, default="technical")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Database schema diagram generated"
     *     )
     * )
     */
    public function generateDatabaseSchemaDiagram(Request $request): JsonResponse
    {
        $style = $request->get('style', 'technical');

        try {
            $diagram = $this->diagramService->generateModelRelationshipDiagram();

            return response()->json([
                'success' => true,
                'diagram' => $diagram,
                'metadata' => [
                    'type' => 'database_schema',
                    'style' => $style,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate schema diagram: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/diagrams/themes",
     *     summary="Get available diagram themes",
     *     description="List all available D2 themes with previews",
     *     tags={"D2 Diagrams"},
     *     @OA\Response(
     *         response=200,
     *         description="Available themes retrieved successfully"
     *     )
     * )
     */
    public function getAvailableThemes(): JsonResponse
    {
        $themes = $this->styleThemes->getAllThemes();

        $themeList = [];
        foreach ($themes as $key => $theme) {
            $themeList[] = [
                'key' => $key,
                'name' => $theme['name'],
                'description' => $theme['description'],
                'color_palette' => $theme['color_palette'],
                'preview_url' => route('api.diagrams.theme-preview', ['theme' => $key])
            ];
        }

        return response()->json([
            'success' => true,
            'themes' => $themeList
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/diagrams/themes/{theme}/preview",
     *     summary="Get theme preview diagram",
     *     description="Generate a preview diagram showing theme styling",
     *     tags={"D2 Diagrams"},
     *     @OA\Parameter(
     *         name="theme",
     *         in="path",
     *         required=true,
     *         description="Theme name"
     *     )
     * )
     */
    public function getThemePreview(string $theme): JsonResponse
    {
        try {
            $diagram = $this->styleThemes->generateThemePreview($theme);

            return response()->json([
                'success' => true,
                'diagram' => $diagram,
                'metadata' => [
                    'theme' => $theme,
                    'type' => 'theme_preview',
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate theme preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/diagrams/templates",
     *     summary="Save D2 diagram template",
     *     description="Save a D2 diagram as a reusable template",
     *     tags={"D2 Diagrams"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "diagram"},
     *             @OA\Property(property="name", type="string", description="Template name"),
     *             @OA\Property(property="diagram", type="string", description="D2 diagram content"),
     *             @OA\Property(property="description", type="string", description="Template description"),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="string"), description="Template tags")
     *         )
     *     )
     * )
     */
    public function saveTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'diagram' => 'required|string',
            'description' => 'sometimes|string|max:1000',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $metadata = [
                'description' => $request->get('description', ''),
                'tags' => $request->get('tags', []),
                'author' => auth()->user()?->name ?? 'Anonymous',
                'created_by' => auth()->id()
            ];

            $templateName = Str::slug($request->name);
            $success = $this->templateEngine->storeTemplate($templateName, $request->diagram, $metadata);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Template saved successfully',
                    'template_name' => $templateName
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to save template'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to save template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/diagrams/templates",
     *     summary="Get available diagram templates",
     *     description="List all saved D2 diagram templates",
     *     tags={"D2 Diagrams"}
     * )
     */
    public function getTemplates(): JsonResponse
    {
        try {
            $templates = $this->templateEngine->getAvailableTemplates();

            return response()->json([
                'success' => true,
                'templates' => $templates
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve templates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prepare rack data for diagram generation
     */
    private function prepareRackData(Rack $rack): array
    {
        // Use Enhanced Analysis chain data if available, otherwise fall back to legacy chains
        $chainData = $this->getEnhancedChainData($rack);

        return [
            'uuid' => $rack->uuid, // Add UUID for D2DiagramService fallback
            'title' => $rack->title,
            'rack_name' => $rack->title,
            'rack_type' => $rack->rack_type,
            'chains' => $chainData,
            'devices' => $rack->devices ?? [],
            'macro_controls' => $rack->macro_controls ?? [],
            'chain_annotations' => $rack->chain_annotations ?? [],
            'ableton_version' => $rack->ableton_version,
            'performance_metrics' => [
                'complexity_score' => $this->calculateComplexityScore($rack),
                'device_count' => $rack->device_count ?? 0,
                'chain_count' => $rack->chain_count ?? 0,
                'cpu_usage' => $this->estimateCpuUsage($rack)
            ],
            'drum_statistics' => $this->extractDrumStatistics($rack)
        ];
    }

    /**
     * Get chain data from Enhanced Analysis if available, otherwise use legacy data
     */
    private function getEnhancedChainData(Rack $rack): array
    {
        // First check if Enhanced Analysis has been completed
        if ($rack->enhanced_analysis_complete && $rack->has_nested_chains) {
            // Load the hierarchical nested chains with their devices
            $nestedChains = $rack->rootNestedChains()
                ->with(['childChains' => function($query) {
                    $query->orderBy('depth_level')->orderBy('chain_identifier');
                }])
                ->orderBy('chain_identifier')
                ->get();

            if ($nestedChains->isNotEmpty()) {
                return $this->convertNestedChainsToLegacyFormat($nestedChains);
            }
        }

        // Fallback to legacy chains data if Enhanced Analysis not available
        return $rack->chains ?? [];
    }

    /**
     * Convert Enhanced Analysis NestedChain models to legacy chain format for D2 compatibility
     */
    private function convertNestedChainsToLegacyFormat($nestedChains): array
    {
        $chains = [];

        foreach ($nestedChains as $nestedChain) {
            $chainData = [
                'name' => $nestedChain->chain_name ?: "Chain {$nestedChain->chain_identifier}",
                'devices' => $this->convertDevicesForD2($nestedChain->devices ?? []),
                'chain_identifier' => $nestedChain->chain_identifier,
                'depth_level' => $nestedChain->depth_level,
                'device_count' => $nestedChain->device_count,
                'is_empty' => $nestedChain->is_empty,
                'chain_type' => $nestedChain->chain_type
            ];

            // Add any child chains as nested devices if they exist
            if ($nestedChain->childChains && $nestedChain->childChains->isNotEmpty()) {
                foreach ($nestedChain->childChains as $childChain) {
                    $chainData['devices'][] = [
                        'name' => "📁 " . ($childChain->chain_name ?: "Chain {$childChain->chain_identifier}"),
                        'type' => 'nested_rack',
                        'device_count' => $childChain->device_count,
                        'is_nested_chain' => true
                    ];
                }
            }

            $chains[] = $chainData;
        }

        return $chains;
    }

    /**
     * Convert Enhanced Analysis device format to legacy format for D2 compatibility
     */
    private function convertDevicesForD2(array $devices): array
    {
        if (empty($devices)) {
            return [];
        }

        $convertedDevices = [];
        foreach ($devices as $device) {
            // Handle both array and object formats
            $deviceData = is_array($device) ? $device : (array) $device;

            $convertedDevices[] = [
                'name' => $deviceData['name'] ?? $deviceData['device_name'] ?? 'Unknown Device',
                'type' => $deviceData['type'] ?? $deviceData['device_type'] ?? 'unknown',
                'parameters' => $deviceData['parameters'] ?? [],
                'is_enabled' => $deviceData['is_enabled'] ?? true
            ];
        }

        return $convertedDevices;
    }

    /**
     * Calculate complexity score for performance metrics
     */
    private function calculateComplexityScore(Rack $rack): int
    {
        $score = 0;
        
        // Base score from device count
        $deviceCount = $rack->device_count ?? 0;
        $score += min($deviceCount * 5, 40); // Max 40 points from devices
        
        // Chain complexity
        $chainCount = $rack->chain_count ?? 0;
        $score += min($chainCount * 3, 20); // Max 20 points from chains
        
        // Nested racks add complexity
        $devices = $rack->devices ?? [];
        foreach ($devices as $device) {
            if (isset($device['chains']) && !empty($device['chains'])) {
                $score += 10; // 10 points per nested rack
            }
        }
        
        // Macro controls add slight complexity
        $macroCount = count($rack->macro_controls ?? []);
        $score += min($macroCount, 10); // Max 10 points from macros
        
        return min($score, 100); // Cap at 100
    }

    /**
     * Estimate CPU usage level
     */
    private function estimateCpuUsage(Rack $rack): string
    {
        $deviceCount = $rack->device_count ?? 0;
        $chainCount = $rack->chain_count ?? 0;
        
        $totalComplexity = $deviceCount + ($chainCount * 2);
        
        if ($totalComplexity > 15) {
            return 'high';
        } elseif ($totalComplexity > 8) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Extract drum-specific statistics
     */
    private function extractDrumStatistics(Rack $rack): array
    {
        if ($rack->rack_type !== 'DrumRack') {
            return [];
        }

        $chains = $rack->chains ?? [];
        $activePads = 0;
        $padMapping = [];

        foreach ($chains as $index => $chain) {
            if (!empty($chain['devices'])) {
                $activePads++;
                
                // Try to map chain to MIDI note based on name or position
                $midiNote = 36 + $index; // Start from C1
                $chainName = $chain['name'] ?? '';
                
                if (str_contains(strtolower($chainName), 'kick')) {
                    $midiNote = 36; // C1
                } elseif (str_contains(strtolower($chainName), 'snare')) {
                    $midiNote = 38; // D1
                } elseif (str_contains(strtolower($chainName), 'hat')) {
                    $midiNote = 42; // F#1 for closed hat
                }
                
                $padMapping[array_search($midiNote, array_keys(D2DiagramService::MIDI_NOTE_MAP)) ?: "C1"] = $chainName;
            }
        }

        return [
            'active_pads' => $activePads,
            'total_pads' => 16, // 4x4 grid
            'pad_mapping' => $padMapping
        ];
    }

    /**
     * Render diagram to different formats (placeholder - would integrate with D2 CLI)
     */
    private function renderDiagram(string $d2Content, string $format): string
    {
        // This would integrate with D2 CLI to render to SVG, PNG, or PDF
        // For now, return the D2 content with format annotation
        
        switch ($format) {
            case 'svg':
                return "<!-- SVG rendering would happen here -->\n" . $d2Content;
            case 'png':
                return "<!-- PNG rendering would happen here -->\n" . $d2Content;
            case 'pdf':
                return "<!-- PDF rendering would happen here -->\n" . $d2Content;
            default:
                return $d2Content;
        }
    }
}