<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rack;
use App\Services\EnhancedRackAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Nested Chain Analysis",
 *     description="Enhanced nested chain analysis endpoints with constitutional compliance"
 * )
 */
class NestedChainAnalysisController extends Controller
{
    private EnhancedRackAnalysisService $analysisService;

    public function __construct(EnhancedRackAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
        $this->middleware('auth:sanctum');
        $this->middleware('throttle:60,1')->only(['analyze', 'reanalyze']);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/racks/{uuid}/analyze-nested-chains",
     *     summary="Trigger enhanced nested chain analysis",
     *     description="Triggers comprehensive nested chain analysis ensuring ALL CHAINS are detected (constitutional requirement)",
     *     tags={"Nested Chain Analysis"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *         description="Rack UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="force", type="boolean", description="Force reanalysis even if already completed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Analysis completed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="rack_uuid", type="string"),
     *             @OA\Property(property="analysis_complete", type="boolean"),
     *             @OA\Property(property="constitutional_compliant", type="boolean"),
     *             @OA\Property(property="nested_chains_detected", type="integer"),
     *             @OA\Property(property="max_nesting_depth", type="integer"),
     *             @OA\Property(property="total_devices", type="integer"),
     *             @OA\Property(property="analysis_duration_ms", type="integer"),
     *             @OA\Property(property="processed_at", type="string", format="date-time"),
     *             @OA\Property(property="hierarchy_preview", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Rack not found"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=422, description="Analysis failed")
     * )
     */
    public function analyze(Request $request, string $uuid): JsonResponse
    {
        $rack = Rack::where('uuid', $uuid)->firstOrFail();

        Gate::authorize('view', $rack);

        $request->validate([
            'force' => 'sometimes|boolean'
        ]);

        $force = $request->boolean('force', false);

        $result = $this->analysisService->analyzeRack($rack, $force);

        if (!$result['analysis_complete']) {
            return response()->json([
                'message' => 'Enhanced analysis failed',
                'error_code' => 'ANALYSIS_FAILED',
                'error' => $result['error'] ?? 'Unknown error occurred',
                'rack_uuid' => $uuid
            ], 422);
        }

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/racks/{uuid}/nested-chains",
     *     summary="Get nested chain hierarchy",
     *     description="Retrieves the complete nested chain hierarchy for a rack",
     *     tags={"Nested Chain Analysis"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *         description="Rack UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="include_devices",
     *         in="query",
     *         description="Include device details in response",
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Nested chain hierarchy retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="rack_uuid", type="string"),
     *             @OA\Property(property="total_chains", type="integer"),
     *             @OA\Property(property="max_depth", type="integer"),
     *             @OA\Property(property="root_chains", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="constitutional_compliant", type="boolean"),
     *             @OA\Property(property="analysis_completed_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Rack not found"),
     *     @OA\Response(response=422, description="Enhanced analysis not completed")
     * )
     */
    public function getHierarchy(Request $request, string $uuid): JsonResponse
    {
        $rack = Rack::where('uuid', $uuid)->firstOrFail();

        Gate::authorize('view', $rack);

        $request->validate([
            'include_devices' => 'sometimes|boolean'
        ]);

        $includeDevices = $request->boolean('include_devices', false);

        $hierarchy = $this->analysisService->getNestedChainHierarchy($rack, $includeDevices);

        if ($hierarchy['total_chains'] === 0 && !$rack->hasEnhancedAnalysis()) {
            return response()->json([
                'message' => 'Enhanced analysis not completed for this rack',
                'error_code' => 'ANALYSIS_NOT_COMPLETED',
                'rack_uuid' => $uuid,
                'suggestion' => 'Use POST /racks/{uuid}/analyze-nested-chains to trigger analysis'
            ], 422);
        }

        return response()->json($hierarchy);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/racks/{uuid}/nested-chains/{chainId}",
     *     summary="Get specific nested chain details",
     *     description="Retrieves detailed information about a specific nested chain",
     *     tags={"Nested Chain Analysis"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *         description="Rack UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="chainId",
     *         in="path",
     *         required=true,
     *         description="Chain ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chain details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="chain_identifier", type="string"),
     *             @OA\Property(property="xml_path", type="string"),
     *             @OA\Property(property="depth_level", type="integer"),
     *             @OA\Property(property="device_count", type="integer"),
     *             @OA\Property(property="is_empty", type="boolean"),
     *             @OA\Property(property="chain_type", type="string"),
     *             @OA\Property(property="devices", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="parameters", type="object"),
     *             @OA\Property(property="parent_chain", type="object"),
     *             @OA\Property(property="child_chains", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="hierarchical_path", type="string"),
     *             @OA\Property(property="constitutional_compliant", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Rack or chain not found")
     * )
     */
    public function getChainDetails(string $uuid, int $chainId): JsonResponse
    {
        $rack = Rack::where('uuid', $uuid)->firstOrFail();

        Gate::authorize('view', $rack);

        $chainDetails = $this->analysisService->getNestedChainDetails($rack, $chainId);

        if (!$chainDetails) {
            return response()->json([
                'message' => 'Chain not found',
                'error_code' => 'CHAIN_NOT_FOUND',
                'chain_id' => $chainId,
                'rack_uuid' => $uuid
            ], 404);
        }

        return response()->json($chainDetails);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/racks/{uuid}/reanalyze-nested-chains",
     *     summary="Reanalyze nested chains with enhanced settings",
     *     description="Forces reanalysis of nested chains with enhanced options",
     *     tags={"Nested Chain Analysis"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *         description="Rack UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="options", type="object", description="Reanalysis options")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reanalysis completed successfully"
     *     ),
     *     @OA\Response(response=404, description="Rack not found"),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function reanalyze(Request $request, string $uuid): JsonResponse
    {
        $rack = Rack::where('uuid', $uuid)->firstOrFail();

        Gate::authorize('update', $rack);

        $request->validate([
            'options' => 'sometimes|array'
        ]);

        $options = $request->input('options', []);

        $result = $this->analysisService->reanalyzeRack($rack, $options);

        if (!$result['analysis_complete']) {
            return response()->json([
                'message' => 'Reanalysis failed',
                'error_code' => 'REANALYSIS_FAILED',
                'error' => $result['error'] ?? 'Unknown error occurred',
                'rack_uuid' => $uuid
            ], 422);
        }

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/racks/{uuid}/analysis-summary",
     *     summary="Get analysis summary",
     *     description="Retrieves a summary of the enhanced analysis for a rack",
     *     tags={"Nested Chain Analysis"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *         description="Rack UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Analysis summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="analyzed", type="boolean"),
     *             @OA\Property(property="constitutional_compliant", type="boolean"),
     *             @OA\Property(property="performance_rating", type="string"),
     *             @OA\Property(property="complexity_rating", type="string"),
     *             @OA\Property(property="efficiency_score", type="number"),
     *             @OA\Property(property="analysis_age_days", type="integer"),
     *             @OA\Property(property="recent_analysis", type="boolean"),
     *             @OA\Property(property="chain_hierarchy_available", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Rack not found")
     * )
     */
    public function getSummary(string $uuid): JsonResponse
    {
        $rack = Rack::where('uuid', $uuid)->firstOrFail();

        Gate::authorize('view', $rack);

        $summary = $this->analysisService->getAnalysisSummary($rack);

        return response()->json($summary);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analysis/bulk-statistics",
     *     summary="Get bulk analysis statistics",
     *     description="Retrieves analysis statistics for multiple racks (admin only)",
     *     tags={"Nested Chain Analysis"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="rack_ids",
     *         in="query",
     *         description="Comma-separated list of rack IDs",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bulk statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="total_analyzed", type="integer"),
     *             @OA\Property(property="average_duration_ms", type="integer"),
     *             @OA\Property(property="average_chains_detected", type="number"),
     *             @OA\Property(property="average_max_depth", type="number"),
     *             @OA\Property(property="constitutional_compliance_rate", type="number")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Admin access required")
     * )
     */
    public function getBulkStatistics(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Rack::class);

        $request->validate([
            'rack_ids' => 'required|string'
        ]);

        $rackIds = array_map('intval', explode(',', $request->input('rack_ids')));

        // Limit to 100 racks for performance
        if (count($rackIds) > 100) {
            throw ValidationException::withMessages([
                'rack_ids' => ['Maximum 100 rack IDs allowed for bulk statistics']
            ]);
        }

        $statistics = $this->analysisService->getBulkAnalysisStatistics($rackIds);

        return response()->json($statistics);
    }
}