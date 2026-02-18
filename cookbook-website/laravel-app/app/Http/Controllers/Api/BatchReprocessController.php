<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rack;
use App\Models\User;
use App\Services\BatchReprocessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Batch Reprocess",
 *     description="Enterprise batch reprocessing endpoints with constitutional compliance monitoring"
 * )
 */
class BatchReprocessController extends Controller
{
    private BatchReprocessService $batchService;

    public function __construct(BatchReprocessService $batchService)
    {
        $this->batchService = $batchService;
        $this->middleware('auth:sanctum');
        $this->middleware('throttle:10,1')->only(['submitBatch']);
        $this->middleware('throttle:60,1')->only(['getBatchStatus', 'getBatchResults']);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/analysis/batch-reprocess",
     *     summary="Submit batch reprocessing request",
     *     description="Submit multiple racks for enhanced nested chain reanalysis (max 10 racks per batch)",
     *     tags={"Batch Reprocess"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rack_uuids"},
     *             @OA\Property(
     *                 property="rack_uuids",
     *                 type="array",
     *                 maxItems=10,
     *                 description="Array of rack UUIDs to reprocess",
     *                 @OA\Items(type="string", format="uuid")
     *             ),
     *             @OA\Property(
     *                 property="priority",
     *                 type="string",
     *                 enum={"low", "normal", "high"},
     *                 default="normal",
     *                 description="Processing priority level"
     *             ),
     *             @OA\Property(
     *                 property="force",
     *                 type="boolean",
     *                 default=false,
     *                 description="Force reanalysis even if already completed"
     *             ),
     *             @OA\Property(
     *                 property="notify_completion",
     *                 type="boolean",
     *                 default=true,
     *                 description="Send notification when batch completes"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Batch submission successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="batch_id", type="string"),
     *             @OA\Property(property="queued_count", type="integer"),
     *             @OA\Property(property="estimated_duration_minutes", type="integer"),
     *             @OA\Property(property="priority", type="string"),
     *             @OA\Property(property="constitutional_compliance_monitoring", type="boolean"),
     *             @OA\Property(property="status_endpoint", type="string"),
     *             @OA\Property(property="results_endpoint", type="string"),
     *             @OA\Property(property="submitted_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid request or batch size exceeded"),
     *     @OA\Response(response=403, description="Unauthorized to reprocess one or more racks"),
     *     @OA\Response(response=422, description="Validation errors")
     * )
     */
    public function submitBatch(Request $request): JsonResponse
    {
        $request->validate([
            'rack_uuids' => 'required|array|min:1|max:10',
            'rack_uuids.*' => 'required|string|uuid',
            'priority' => 'sometimes|string|in:low,normal,high',
            'force' => 'sometimes|boolean',
            'notify_completion' => 'sometimes|boolean'
        ]);

        $rackUuids = $request->input('rack_uuids');
        $priority = $request->input('priority', 'normal');
        $force = $request->boolean('force', false);
        $notifyCompletion = $request->boolean('notify_completion', true);

        $user = Auth::user();

        // Validate all racks exist and user has access
        $racks = Rack::whereIn('uuid', $rackUuids)->get();

        if ($racks->count() !== count($rackUuids)) {
            return response()->json([
                'message' => 'One or more racks not found',
                'error_code' => 'RACKS_NOT_FOUND'
            ], 404);
        }

        // Check authorization for each rack
        foreach ($racks as $rack) {
            if (!$user->hasRole('admin') && $rack->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Unauthorized to reprocess rack: ' . $rack->uuid,
                    'error_code' => 'UNAUTHORIZED_RACK_ACCESS',
                    'rack_uuid' => $rack->uuid
                ], 403);
            }
        }

        try {
            $result = $this->batchService->submitBatchReprocess($rackUuids, $user, [
                'priority' => $priority,
                'force' => $force,
                'notify_completion' => $notifyCompletion
            ]);

            return response()->json([
                'batch_id' => $result['batch_id'],
                'queued_count' => $result['queued_count'],
                'estimated_duration_minutes' => $result['estimated_duration_minutes'],
                'priority' => $priority,
                'constitutional_compliance_monitoring' => true,
                'status_endpoint' => "/api/v1/analysis/batch-status/{$result['batch_id']}",
                'results_endpoint' => "/api/v1/analysis/batch-results/{$result['batch_id']}",
                'submitted_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to submit batch reprocess',
                'error_code' => 'BATCH_SUBMISSION_FAILED',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analysis/batch-status/{batchId}",
     *     summary="Get batch processing status",
     *     description="Retrieve real-time status of a batch reprocessing operation",
     *     tags={"Batch Reprocess"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="batchId",
     *         in="path",
     *         required=true,
     *         description="Batch ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Batch status retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="batch_id", type="string"),
     *             @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed"}),
     *             @OA\Property(property="total_racks", type="integer"),
     *             @OA\Property(property="completed_racks", type="integer"),
     *             @OA\Property(property="failed_racks", type="integer"),
     *             @OA\Property(property="progress_percentage", type="number"),
     *             @OA\Property(property="estimated_completion", type="string", format="date-time"),
     *             @OA\Property(property="constitutional_compliance_summary", type="object",
     *                 @OA\Property(property="compliant_racks", type="integer"),
     *                 @OA\Property(property="non_compliant_racks", type="integer"),
     *                 @OA\Property(property="compliance_rate", type="number")
     *             ),
     *             @OA\Property(property="priority", type="string"),
     *             @OA\Property(property="submitted_at", type="string", format="date-time"),
     *             @OA\Property(property="started_at", type="string", format="date-time"),
     *             @OA\Property(property="completed_at", type="string", format="date-time"),
     *             @OA\Property(property="current_rack", type="string", description="Currently processing rack UUID")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Batch not found"),
     *     @OA\Response(response=403, description="Unauthorized to view this batch")
     * )
     */
    public function getBatchStatus(string $batchId): JsonResponse
    {
        $user = Auth::user();

        try {
            $status = $this->batchService->getBatchStatus($batchId, $user);

            if (!$status) {
                return response()->json([
                    'message' => 'Batch not found',
                    'error_code' => 'BATCH_NOT_FOUND',
                    'batch_id' => $batchId
                ], 404);
            }

            return response()->json($status);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve batch status',
                'error_code' => 'BATCH_STATUS_ERROR',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analysis/batch-results/{batchId}",
     *     summary="Get batch processing results",
     *     description="Retrieve detailed results of a completed batch reprocessing operation",
     *     tags={"Batch Reprocess"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="batchId",
     *         in="path",
     *         required=true,
     *         description="Batch ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="include_details",
     *         in="query",
     *         description="Include detailed analysis results for each rack",
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Batch results retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="batch_id", type="string"),
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="summary", type="object",
     *                 @OA\Property(property="total_processed", type="integer"),
     *                 @OA\Property(property="successful", type="integer"),
     *                 @OA\Property(property="failed", type="integer"),
     *                 @OA\Property(property="constitutional_compliant", type="integer"),
     *                 @OA\Property(property="total_chains_detected", type="integer"),
     *                 @OA\Property(property="average_analysis_duration_ms", type="number"),
     *                 @OA\Property(property="total_processing_time_seconds", type="number")
     *             ),
     *             @OA\Property(property="rack_results", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="rack_uuid", type="string"),
     *                 @OA\Property(property="success", type="boolean"),
     *                 @OA\Property(property="constitutional_compliant", type="boolean"),
     *                 @OA\Property(property="nested_chains_detected", type="integer"),
     *                 @OA\Property(property="analysis_duration_ms", type="integer"),
     *                 @OA\Property(property="error", type="string"),
     *                 @OA\Property(property="processed_at", type="string", format="date-time")
     *             )),
     *             @OA\Property(property="constitutional_compliance_report", type="object"),
     *             @OA\Property(property="submitted_at", type="string", format="date-time"),
     *             @OA\Property(property="completed_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Batch not found"),
     *     @OA\Response(response=422, description="Batch not yet completed")
     * )
     */
    public function getBatchResults(Request $request, string $batchId): JsonResponse
    {
        $request->validate([
            'include_details' => 'sometimes|boolean'
        ]);

        $includeDetails = $request->boolean('include_details', false);
        $user = Auth::user();

        try {
            $results = $this->batchService->getBatchResults($batchId, $user, $includeDetails);

            if (!$results) {
                return response()->json([
                    'message' => 'Batch not found',
                    'error_code' => 'BATCH_NOT_FOUND',
                    'batch_id' => $batchId
                ], 404);
            }

            if ($results['status'] !== 'completed' && $results['status'] !== 'failed') {
                return response()->json([
                    'message' => 'Batch processing not yet completed',
                    'error_code' => 'BATCH_NOT_COMPLETED',
                    'batch_id' => $batchId,
                    'current_status' => $results['status'],
                    'suggestion' => 'Use GET /batch-status/{batchId} to monitor progress'
                ], 422);
            }

            return response()->json($results);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve batch results',
                'error_code' => 'BATCH_RESULTS_ERROR',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analysis/batch-history",
     *     summary="Get user's batch processing history",
     *     description="Retrieve paginated history of batch operations for the authenticated user",
     *     tags={"Batch Reprocess"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page (max 50)",
     *         @OA\Schema(type="integer", default=20, maximum=50)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by batch status",
     *         @OA\Schema(type="string", enum={"pending", "processing", "completed", "failed"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Batch history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object"),
     *             @OA\Property(property="links", type="object")
     *         )
     *     )
     * )
     */
    public function getBatchHistory(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'status' => 'sometimes|string|in:pending,processing,completed,failed'
        ]);

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);
        $status = $request->input('status');
        $user = Auth::user();

        try {
            $history = $this->batchService->getUserBatchHistory($user, [
                'page' => $page,
                'per_page' => $perPage,
                'status' => $status
            ]);

            return response()->json($history);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve batch history',
                'error_code' => 'BATCH_HISTORY_ERROR',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/analysis/batch/{batchId}",
     *     summary="Cancel pending batch operation",
     *     description="Cancel a pending or processing batch operation (admin or batch owner only)",
     *     tags={"Batch Reprocess"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="batchId",
     *         in="path",
     *         required=true,
     *         description="Batch ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Batch cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="batch_id", type="string"),
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="cancelled_at", type="string", format="date-time"),
     *             @OA\Property(property="racks_processed", type="integer"),
     *             @OA\Property(property="racks_cancelled", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Batch not found"),
     *     @OA\Response(response=403, description="Unauthorized to cancel this batch"),
     *     @OA\Response(response=422, description="Batch cannot be cancelled")
     * )
     */
    public function cancelBatch(string $batchId): JsonResponse
    {
        $user = Auth::user();

        try {
            $result = $this->batchService->cancelBatch($batchId, $user);

            if (!$result) {
                return response()->json([
                    'message' => 'Batch not found or cannot be cancelled',
                    'error_code' => 'BATCH_CANCEL_FAILED',
                    'batch_id' => $batchId
                ], 404);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel batch',
                'error_code' => 'BATCH_CANCEL_ERROR',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}