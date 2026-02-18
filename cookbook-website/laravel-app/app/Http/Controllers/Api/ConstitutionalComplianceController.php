<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rack;
use App\Services\ConstitutionalComplianceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Tag(
 *     name="Constitutional Compliance",
 *     description="Constitutional governance and compliance reporting endpoints for Ableton Cookbook"
 * )
 */
class ConstitutionalComplianceController extends Controller
{
    private ConstitutionalComplianceService $complianceService;

    public function __construct(ConstitutionalComplianceService $complianceService)
    {
        $this->complianceService = $complianceService;
        $this->middleware('auth:sanctum');
        $this->middleware('throttle:60,1')->only(['validateRack', 'getRackCompliance']);
        $this->middleware('throttle:30,1')->only(['getSystemCompliance', 'getComplianceReport']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/compliance/constitution",
     *     summary="Get current constitutional version and requirements",
     *     description="Retrieve the active constitutional requirements for enhanced rack analysis",
     *     tags={"Constitutional Compliance"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Constitutional information retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="version", type="string", example="1.1.0"),
     *             @OA\Property(property="effective_date", type="string", format="date"),
     *             @OA\Property(property="requirements", type="object",
     *                 @OA\Property(property="all_chains_detection", type="object",
     *                     @OA\Property(property="mandatory", type="boolean", example=true),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="enforcement_level", type="string", example="strict")
     *                 ),
     *                 @OA\Property(property="performance_limits", type="object",
     *                     @OA\Property(property="max_analysis_time_seconds", type="integer", example=5),
     *                     @OA\Property(property="timeout_enforcement", type="boolean", example=true)
     *                 ),
     *                 @OA\Property(property="data_integrity", type="object",
     *                     @OA\Property(property="chain_hierarchy_preservation", type="boolean", example=true),
     *                     @OA\Property(property="device_relationship_accuracy", type="boolean", example=true)
     *                 )
     *             ),
     *             @OA\Property(property="compliance_standards", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="validation_criteria", type="object"),
     *             @OA\Property(property="last_updated", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function getConstitution(): JsonResponse
    {
        $constitution = $this->complianceService->getCurrentConstitution();
        return response()->json($constitution);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/compliance/validate-rack/{uuid}",
     *     summary="Validate rack constitutional compliance",
     *     description="Perform comprehensive constitutional compliance validation for a specific rack",
     *     tags={"Constitutional Compliance"},
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
     *             @OA\Property(property="strict_mode", type="boolean", default=true, description="Enable strict constitutional validation"),
     *             @OA\Property(property="generate_report", type="boolean", default=false, description="Generate detailed compliance report"),
     *             @OA\Property(property="include_recommendations", type="boolean", default=true, description="Include improvement recommendations")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compliance validation completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="rack_uuid", type="string"),
     *             @OA\Property(property="constitutional_compliant", type="boolean"),
     *             @OA\Property(property="compliance_score", type="number", minimum=0, maximum=100),
     *             @OA\Property(property="validation_results", type="object",
     *                 @OA\Property(property="all_chains_detected", type="object",
     *                     @OA\Property(property="compliant", type="boolean"),
     *                     @OA\Property(property="detected_chains", type="integer"),
     *                     @OA\Property(property="expected_minimum", type="integer"),
     *                     @OA\Property(property="detection_accuracy", type="number")
     *                 ),
     *                 @OA\Property(property="performance_compliance", type="object",
     *                     @OA\Property(property="compliant", type="boolean"),
     *                     @OA\Property(property="analysis_duration_ms", type="integer"),
     *                     @OA\Property(property="time_limit_ms", type="integer", example=5000),
     *                     @OA\Property(property="performance_score", type="number")
     *                 ),
     *                 @OA\Property(property="data_integrity", type="object",
     *                     @OA\Property(property="compliant", type="boolean"),
     *                     @OA\Property(property="hierarchy_preserved", type="boolean"),
     *                     @OA\Property(property="relationships_accurate", type="boolean"),
     *                     @OA\Property(property="data_completeness", type="number")
     *                 )
     *             ),
     *             @OA\Property(property="compliance_issues", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="category", type="string"),
     *                 @OA\Property(property="severity", type="string", enum={"low", "medium", "high", "critical"}),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="recommendation", type="string")
     *             )),
     *             @OA\Property(property="recommendations", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="constitution_version", type="string"),
     *             @OA\Property(property="validated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Rack not found"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
    public function validateRack(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'strict_mode' => 'sometimes|boolean',
            'generate_report' => 'sometimes|boolean',
            'include_recommendations' => 'sometimes|boolean'
        ]);

        $rack = Rack::where('uuid', $uuid)->firstOrFail();
        Gate::authorize('view', $rack);

        $options = [
            'strict_mode' => $request->boolean('strict_mode', true),
            'generate_report' => $request->boolean('generate_report', false),
            'include_recommendations' => $request->boolean('include_recommendations', true)
        ];

        try {
            $validation = $this->complianceService->validateRackCompliance($rack, $options);
            return response()->json($validation);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Constitutional validation failed',
                'error_code' => 'VALIDATION_FAILED',
                'error' => $e->getMessage(),
                'rack_uuid' => $uuid
            ], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/compliance/rack/{uuid}",
     *     summary="Get rack compliance status",
     *     description="Retrieve current constitutional compliance status for a rack",
     *     tags={"Constitutional Compliance"},
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
     *         description="Compliance status retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="rack_uuid", type="string"),
     *             @OA\Property(property="constitutional_compliant", type="boolean"),
     *             @OA\Property(property="compliance_score", type="number"),
     *             @OA\Property(property="last_validation", type="string", format="date-time"),
     *             @OA\Property(property="constitution_version", type="string"),
     *             @OA\Property(property="requires_revalidation", type="boolean"),
     *             @OA\Property(property="compliance_summary", type="object",
     *                 @OA\Property(property="chains_detection_compliant", type="boolean"),
     *                 @OA\Property(property="performance_compliant", type="boolean"),
     *                 @OA\Property(property="data_integrity_compliant", type="boolean")
     *             ),
     *             @OA\Property(property="enhancement_suggestions", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Rack not found or not analyzed")
     * )
     */
    public function getRackCompliance(string $uuid): JsonResponse
    {
        $rack = Rack::where('uuid', $uuid)->firstOrFail();
        Gate::authorize('view', $rack);

        $compliance = $this->complianceService->getRackComplianceStatus($rack);

        if (!$compliance) {
            return response()->json([
                'message' => 'Compliance status not available',
                'error_code' => 'COMPLIANCE_NOT_AVAILABLE',
                'rack_uuid' => $uuid,
                'suggestion' => 'Run enhanced analysis first using POST /racks/{uuid}/analyze-nested-chains'
            ], 404);
        }

        return response()->json($compliance);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/compliance/system-status",
     *     summary="Get system-wide compliance status",
     *     description="Retrieve constitutional compliance overview for the entire system (admin only)",
     *     tags={"Constitutional Compliance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="include_statistics",
     *         in="query",
     *         description="Include detailed compliance statistics",
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Parameter(
     *         name="time_range",
     *         in="query",
     *         description="Time range for compliance metrics",
     *         @OA\Schema(type="string", enum={"24h", "7d", "30d", "90d"}, default="30d")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="System compliance status retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="system_compliance_score", type="number"),
     *             @OA\Property(property="constitution_version", type="string"),
     *             @OA\Property(property="total_racks_analyzed", type="integer"),
     *             @OA\Property(property="compliant_racks", type="integer"),
     *             @OA\Property(property="non_compliant_racks", type="integer"),
     *             @OA\Property(property="compliance_rate", type="number"),
     *             @OA\Property(property="compliance_trends", type="object",
     *                 @OA\Property(property="daily_compliance_rates", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="improvement_trend", type="string", enum={"improving", "stable", "declining"}),
     *                 @OA\Property(property="trend_percentage", type="number")
     *             ),
     *             @OA\Property(property="common_compliance_issues", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="issue_type", type="string"),
     *                 @OA\Property(property="frequency", type="integer"),
     *                 @OA\Property(property="impact_level", type="string")
     *             )),
     *             @OA\Property(property="performance_metrics", type="object",
     *                 @OA\Property(property="average_analysis_time_ms", type="number"),
     *                 @OA\Property(property="timeout_rate", type="number"),
     *                 @OA\Property(property="performance_compliance_rate", type="number")
     *             ),
     *             @OA\Property(property="system_recommendations", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="last_updated", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Admin access required")
     * )
     */
    public function getSystemCompliance(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Rack::class);

        $request->validate([
            'include_statistics' => 'sometimes|boolean',
            'time_range' => 'sometimes|string|in:24h,7d,30d,90d'
        ]);

        $includeStatistics = $request->boolean('include_statistics', true);
        $timeRange = $request->input('time_range', '30d');

        $systemCompliance = $this->complianceService->getSystemComplianceStatus([
            'include_statistics' => $includeStatistics,
            'time_range' => $timeRange
        ]);

        return response()->json($systemCompliance);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/compliance/report",
     *     summary="Generate comprehensive compliance report",
     *     description="Generate detailed constitutional compliance report (admin only)",
     *     tags={"Constitutional Compliance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Report format",
     *         @OA\Schema(type="string", enum={"json", "pdf", "csv"}, default="json")
     *     ),
     *     @OA\Parameter(
     *         name="scope",
     *         in="query",
     *         description="Report scope",
     *         @OA\Schema(type="string", enum={"all", "non_compliant", "recent"}, default="all")
     *     ),
     *     @OA\Parameter(
     *         name="include_details",
     *         in="query",
     *         description="Include detailed rack-level information",
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compliance report generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="report_id", type="string"),
     *             @OA\Property(property="generated_at", type="string", format="date-time"),
     *             @OA\Property(property="constitution_version", type="string"),
     *             @OA\Property(property="report_scope", type="string"),
     *             @OA\Property(property="executive_summary", type="object",
     *                 @OA\Property(property="overall_compliance_score", type="number"),
     *                 @OA\Property(property="total_racks_evaluated", type="integer"),
     *                 @OA\Property(property="compliance_rate", type="number"),
     *                 @OA\Property(property="critical_issues_count", type="integer"),
     *                 @OA\Property(property="system_health_score", type="number")
     *             ),
     *             @OA\Property(property="compliance_breakdown", type="object"),
     *             @OA\Property(property="performance_analysis", type="object"),
     *             @OA\Property(property="recommendations", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="detailed_findings", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="download_url", type="string"),
     *             @OA\Property(property="expires_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Admin access required")
     * )
     */
    public function getComplianceReport(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Rack::class);

        $request->validate([
            'format' => 'sometimes|string|in:json,pdf,csv',
            'scope' => 'sometimes|string|in:all,non_compliant,recent',
            'include_details' => 'sometimes|boolean'
        ]);

        $format = $request->input('format', 'json');
        $scope = $request->input('scope', 'all');
        $includeDetails = $request->boolean('include_details', false);

        try {
            $report = $this->complianceService->generateComplianceReport([
                'format' => $format,
                'scope' => $scope,
                'include_details' => $includeDetails
            ]);

            return response()->json($report);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate compliance report',
                'error_code' => 'REPORT_GENERATION_FAILED',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/compliance/audit-log",
     *     summary="Log compliance audit event",
     *     description="Record a constitutional compliance audit event for tracking and governance",
     *     tags={"Constitutional Compliance"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"event_type", "rack_uuid"},
     *             @OA\Property(property="event_type", type="string", enum={"validation_requested", "compliance_verified", "issue_discovered", "remediation_completed"}),
     *             @OA\Property(property="rack_uuid", type="string", format="uuid"),
     *             @OA\Property(property="compliance_score", type="number"),
     *             @OA\Property(property="issues_identified", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="actions_taken", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="metadata", type="object"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Audit event logged successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="audit_id", type="string"),
     *             @OA\Property(property="event_type", type="string"),
     *             @OA\Property(property="logged_at", type="string", format="date-time"),
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="constitution_version", type="string")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation errors")
     * )
     */
    public function logAuditEvent(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => 'required|string|in:validation_requested,compliance_verified,issue_discovered,remediation_completed',
            'rack_uuid' => 'required|string|uuid',
            'compliance_score' => 'sometimes|numeric|min:0|max:100',
            'issues_identified' => 'sometimes|array',
            'issues_identified.*' => 'string',
            'actions_taken' => 'sometimes|array',
            'actions_taken.*' => 'string',
            'metadata' => 'sometimes|array',
            'notes' => 'sometimes|string|max:1000'
        ]);

        $user = Auth::user();
        $eventData = $request->all();
        $eventData['user_id'] = $user->id;

        try {
            $auditEvent = $this->complianceService->logAuditEvent($eventData);

            return response()->json($auditEvent, 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to log audit event',
                'error_code' => 'AUDIT_LOG_FAILED',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/compliance/version-history",
     *     summary="Get constitutional version history",
     *     description="Retrieve historical versions of the constitutional requirements",
     *     tags={"Constitutional Compliance"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Version history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="current_version", type="string"),
     *             @OA\Property(property="versions", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="version", type="string"),
     *                 @OA\Property(property="effective_date", type="string", format="date"),
     *                 @OA\Property(property="deprecated_date", type="string", format="date"),
     *                 @OA\Property(property="major_changes", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="compatibility_impact", type="string"),
     *                 @OA\Property(property="migration_required", type="boolean")
     *             )),
     *             @OA\Property(property="upcoming_changes", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getVersionHistory(): JsonResponse
    {
        $versionHistory = $this->complianceService->getConstitutionalVersionHistory();
        return response()->json($versionHistory);
    }
}