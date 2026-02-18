<?php

namespace App\Http\Controllers\Api;

use App\Enums\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRackRequest;
use App\Http\Requests\UpdateRackRequest;
use App\Http\Requests\UpdateHowToRequest;
use App\Http\Responses\ErrorResponse;
use App\Jobs\IncrementRackViewsJob;
use App\Models\Rack;
use App\Services\RackProcessingService;
use App\Services\EnhancedRackAnalysisService;
use App\Services\ConstitutionalComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 *     name="Racks",
 *     description="API Endpoints for managing Ableton Live racks"
 * )
 */
class RackController extends Controller
{
    protected RackProcessingService $rackService;
    protected EnhancedRackAnalysisService $enhancedAnalysisService;
    protected ConstitutionalComplianceService $complianceService;

    public function __construct(
        RackProcessingService $rackService,
        EnhancedRackAnalysisService $enhancedAnalysisService,
        ConstitutionalComplianceService $complianceService
    ) {
        $this->rackService = $rackService;
        $this->enhancedAnalysisService = $enhancedAnalysisService;
        $this->complianceService = $complianceService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/racks",
     *     summary="Get a list of racks",
     *     description="Retrieve a paginated list of published racks with optional filtering and sorting",
     *     operationId="getRacks",
     *     tags={"Racks"},
     *     @OA\Parameter(
     *         name="filter[rack_type]",
     *         in="query",
     *         description="Filter by rack type (instrument, audio_effect, midi_effect)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"instrument", "audio_effect", "midi_effect"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[user_id]",
     *         in="query",
     *         description="Filter by user ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="filter[tags]",
     *         in="query",
     *         description="Filter by tags (comma-separated)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filter[rating]",
     *         in="query",
     *         description="Filter by minimum rating",
     *         required=false,
     *         @OA\Schema(type="number", format="float", minimum=0, maximum=5)
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort by field (prefix with - for descending)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"-created_at", "created_at", "-downloads_count", "downloads_count", "-average_rating", "average_rating", "-views_count", "views_count"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="object")
     *             ),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Add loading state for slow queries
            $isSlowQuery = $request->has('filter') && count($request->get('filter', [])) > 2;
            
            if ($isSlowQuery) {
                // Return early loading response for complex queries
                return ErrorResponse::loading('Processing search filters...', [
                    'estimated_time' => '2-5 seconds',
                    'filters_applied' => count($request->get('filter', []))
                ]);
            }
            
            $racks = QueryBuilder::for(Rack::class)
                ->published()
                ->with(['user:id,name,profile_photo_path', 'tags', 'enhancedAnalysis:rack_id,constitutional_compliant,nested_chains_detected,analysis_complete'])
                ->allowedFilters([
                    AllowedFilter::exact('rack_type'),
                    AllowedFilter::exact('user_id'),
                    AllowedFilter::scope('featured'),
                    AllowedFilter::callback('devices', function ($query, $value) {
                        $query->whereJsonContains('devices', $value);
                    }),
                    AllowedFilter::callback('tags', function ($query, $value) {
                        $query->whereHas('tags', function ($q) use ($value) {
                            $q->whereIn('slug', explode(',', $value));
                        });
                    }),
                    AllowedFilter::callback('rating', function ($query, $value) {
                        $query->where('average_rating', '>=', $value);
                    }),
                    AllowedFilter::callback('constitutional_compliant', function ($query, $value) {
                        $query->whereHas('enhancedAnalysis', function ($q) use ($value) {
                            $q->where('constitutional_compliant', (bool) $value);
                        });
                    }),
                    AllowedFilter::callback('has_enhanced_analysis', function ($query, $value) {
                        if ($value) {
                            $query->whereHas('enhancedAnalysis', function ($q) {
                                $q->where('analysis_complete', true);
                            });
                        } else {
                            $query->whereDoesntHave('enhancedAnalysis');
                        }
                    }),
                ])
                ->allowedSorts(['created_at', 'downloads_count', 'average_rating', 'views_count'])
                ->defaultSort('-created_at')
                ->paginate($request->get('per_page', 20));

            return ErrorResponse::success($racks, null, [
                'query_time' => microtime(true) - LARAVEL_START,
                'cache_hit' => false, // Could be determined by your caching strategy
                'total_results' => $racks->total(),
            ]);
            
        } catch (\Illuminate\Database\QueryException $e) {
            return ErrorResponse::create(
                ErrorCode::DATABASE_ERROR,
                'Failed to retrieve racks',
                ['operation' => 'index', 'filters' => $request->get('filter', [])],
                null,
                $e
            );
        } catch (\Exception $e) {
            return ErrorResponse::create(
                ErrorCode::TEMPORARY_FAILURE,
                'An unexpected error occurred while loading racks',
                ['operation' => 'index'],
                null,
                $e
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/racks",
     *     summary="Upload a new rack",
     *     description="Create a new rack by uploading an Ableton device group (.adg) file",
     *     operationId="createRack",
     *     tags={"Racks"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Ableton device group (.adg) file"
     *                 ),
     *                 @OA\Property(
     *                     property="title",
     *                     type="string",
     *                     maxLength=255,
     *                     description="Rack title"
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     maxLength=1000,
     *                     description="Rack description"
     *                 ),
     *                 @OA\Property(
     *                     property="tags",
     *                     type="array",
     *                     maxItems=10,
     *                     @OA\Items(type="string", maxLength=50),
     *                     description="Tags for the rack"
     *                 ),
     *                 @OA\Property(
     *                     property="is_public",
     *                     type="boolean",
     *                     description="Whether the rack is public"
     *                 ),
     *                 required={"file", "title"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Rack created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="rack", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Duplicate rack file",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="rack", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or processing failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function store(StoreRackRequest $request): JsonResponse
    {
        try {
            // Return immediate loading response for file upload
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                
                // Validate file before processing
                if (!$file->isValid()) {
                    return ErrorResponse::create(
                        ErrorCode::UPLOAD_FAILED,
                        'File upload failed',
                        ['file_error' => $file->getErrorMessage()]
                    );
                }
                
                // Check file size
                if ($file->getSize() > 50 * 1024 * 1024) { // 50MB limit
                    return ErrorResponse::create(
                        ErrorCode::FILE_TOO_LARGE,
                        null,
                        ['file_size' => $file->getSize(), 'max_size' => 50 * 1024 * 1024]
                    );
                }
                
                // Check file type
                if ($file->getClientOriginalExtension() !== 'adg') {
                    return ErrorResponse::create(
                        ErrorCode::INVALID_FILE_TYPE,
                        null,
                        ['file_type' => $file->getClientOriginalExtension(), 'expected' => 'adg']
                    );
                }
            }

            $validated = $request->validated();

            // Check for duplicate
            $fileHash = hash_file('sha256', $request->file('file')->path());
            if ($duplicate = $this->rackService->isDuplicate($fileHash)) {
                return ErrorResponse::create(
                    ErrorCode::DUPLICATE_ENTRY,
                    null,
                    ['existing_rack_id' => $duplicate->id],
                    ['duplicate_rack' => $duplicate->load(['user:id,name'])]
                );
            }

            // Process the rack with progress tracking
            $rack = $this->rackService->processRack(
                $request->file('file'),
                $request->user(),
                $request->only(['title', 'description', 'tags', 'is_public'])
            );

            $rack->load(['user:id,name,profile_photo_path', 'tags']);

            return ErrorResponse::success(
                $rack,
                'Rack uploaded successfully! Analysis is starting in the background.',
                [
                    'processing_status' => 'queued',
                    'estimated_completion' => now()->addMinutes(2)->toISOString(),
                    'can_proceed_to_metadata' => true
                ]
            );
            
        } catch (ValidationException $e) {
            return ErrorResponse::validation($e->errors());
            
        } catch (\Illuminate\Http\Exceptions\PostTooLargeException $e) {
            return ErrorResponse::create(
                ErrorCode::FILE_TOO_LARGE,
                'The uploaded file exceeds the maximum allowed size'
            );
            
        } catch (\Symfony\Component\HttpFoundation\File\Exception\FileException $e) {
            return ErrorResponse::create(
                ErrorCode::UPLOAD_FAILED,
                null,
                ['file_error' => $e->getMessage()],
                null,
                $e
            );
            
        } catch (\App\Exceptions\RackProcessingException $e) {
            return ErrorResponse::create(
                ErrorCode::PROCESSING_FAILED,
                $e->getMessage(),
                ['processing_stage' => $e->getProcessingStage() ?? 'unknown'],
                null,
                $e
            );
            
        } catch (\Exception $e) {
            return ErrorResponse::create(
                ErrorCode::PROCESSING_FAILED,
                'An unexpected error occurred during file processing',
                ['operation' => 'store'],
                null,
                $e
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/racks/{id}",
     *     summary="Get a specific rack",
     *     description="Retrieve details of a specific rack by ID",
     *     operationId="getRack",
     *     tags={"Racks"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rack ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Rack not found"
     *     )
     * )
     */
    public function show(Rack $rack): JsonResponse
    {
        try {
            // Check if rack is published or user owns it
            if (!$rack->is_public || $rack->status !== 'approved') {
                if (!auth()->check() || $rack->user_id !== auth()->id()) {
                    return ErrorResponse::create(
                        ErrorCode::RESOURCE_NOT_FOUND,
                        'This rack is not publicly available',
                        ['rack_id' => $rack->id, 'is_public' => $rack->is_public, 'status' => $rack->status]
                    );
                }
            }

            // Check if rack was deleted
            if ($rack->status === 'deleted') {
                return ErrorResponse::create(
                    ErrorCode::RESOURCE_DELETED,
                    'This rack has been removed',
                    ['rack_id' => $rack->id, 'deleted_at' => $rack->updated_at]
                );
            }

            $rack->load([
                'user:id,name,profile_photo_path,created_at',
                'tags',
                'comments.user:id,name,profile_photo_path',
                'enhancedAnalysis:rack_id,constitutional_compliant,nested_chains_detected,max_nesting_depth,analysis_complete,processed_at'
            ]);
            
            // Queue view increment to avoid blocking response (with error handling)
            try {
                IncrementRackViewsJob::dispatch($rack->id);
            } catch (\Exception $e) {
                // Log but don't fail the request for view counting errors
                Log::warning('Failed to queue view increment job', [
                    'rack_id' => $rack->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            return ErrorResponse::success(
                $rack,
                null,
                [
                    'view_incremented' => true,
                    'last_updated' => $rack->updated_at->toISOString(),
                    'is_owner' => auth()->check() && auth()->id() === $rack->user_id,
                    'enhanced_analysis_available' => $rack->hasEnhancedAnalysis(),
                    'constitutional_compliant' => $rack->enhancedAnalysis?->constitutional_compliant ?? null,
                    'nested_chains_count' => $rack->enhancedAnalysis?->nested_chains_detected ?? 0
                ]
            );
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ErrorResponse::create(
                ErrorCode::RESOURCE_NOT_FOUND,
                'Rack not found',
                ['rack_id' => $rack->id ?? 'unknown']
            );
            
        } catch (\Exception $e) {
            return ErrorResponse::create(
                ErrorCode::DATABASE_ERROR,
                'Failed to load rack details',
                ['rack_id' => $rack->id, 'operation' => 'show'],
                null,
                $e
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/racks/trending",
     *     summary="Get trending racks",
     *     description="Retrieve racks that are currently trending",
     *     operationId="getTrendingRacks",
     *     tags={"Racks"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of results to return",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=50, default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(type="object")
     *         )
     *     )
     * )
     */
    public function trending(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);
        
        $racks = Rack::published()
            ->with(['user:id,name,profile_photo_path', 'tags'])
            ->where('created_at', '>=', now()->subWeeks(2))
            ->selectRaw('*, (downloads_count * 0.7 + average_rating * ratings_count * 0.3) as trending_score')
            ->orderByDesc('trending_score')
            ->limit($limit)
            ->get();

        return response()->json($racks);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/racks/featured",
     *     summary="Get featured racks",
     *     description="Retrieve racks that are featured by administrators",
     *     operationId="getFeaturedRacks",
     *     tags={"Racks"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of results to return",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=50, default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(type="object")
     *         )
     *     )
     * )
     */
    public function featured(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);
        
        $racks = Rack::featured()
            ->published()
            ->with(['user:id,name,profile_photo_path', 'tags'])
            ->orderByDesc('featured_at')
            ->limit($limit)
            ->get();

        return response()->json($racks);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/racks/{id}",
     *     summary="Update a rack",
     *     description="Update rack details (owner only)",
     *     operationId="updateRack",
     *     tags={"Racks"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rack ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=255),
     *             @OA\Property(property="description", type="string", maxLength=1000),
     *             @OA\Property(property="is_public", type="boolean"),
     *             @OA\Property(
     *                 property="tags",
     *                 type="array",
     *                 maxItems=10,
     *                 @OA\Items(type="string", maxLength=50)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rack updated successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - not the owner"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Rack not found"
     *     )
     * )
     */
    public function update(UpdateRackRequest $request, Rack $rack): JsonResponse
    {
        try {
            // Check authorization
            if (!Gate::allows('update', $rack)) {
                return ErrorResponse::create(
                    ErrorCode::INSUFFICIENT_PERMISSIONS,
                    'You do not have permission to update this rack',
                    ['rack_id' => $rack->id, 'owner_id' => $rack->user_id]
                );
            }

            // Check if rack is locked for editing
            if ($rack->is_locked) {
                return ErrorResponse::create(
                    ErrorCode::RESOURCE_LOCKED,
                    'This rack is currently being edited by another user',
                    [
                        'rack_id' => $rack->id,
                        'locked_by' => $rack->locked_by_user_id,
                        'locked_at' => $rack->locked_at
                    ]
                );
            }

            $validated = $request->validated();
            
            // Check for version conflicts if version is provided
            if ($request->has('version') && $rack->version !== $request->get('version')) {
                return ErrorResponse::create(
                    ErrorCode::VERSION_MISMATCH,
                    'Another user has updated this rack since you started editing',
                    [
                        'current_version' => $rack->version,
                        'provided_version' => $request->get('version'),
                        'last_updated' => $rack->updated_at
                    ]
                );
            }
            
            $updateData = $request->only(['title', 'description', 'is_public', 'how_to_article']);
            
            // If how_to_article is being updated, set the timestamp
            if ($request->has('how_to_article')) {
                $updateData['how_to_updated_at'] = now();
            }
            
            // Add version increment for optimistic locking
            $updateData['version'] = $rack->version + 1;
            
            $rack->update($updateData);
            
            // Handle tags with error handling
            if ($request->has('tags')) {
                try {
                    $this->rackService->syncTags($rack, $request->tags);
                } catch (\Exception $e) {
                    Log::warning('Failed to sync tags during rack update', [
                        'rack_id' => $rack->id,
                        'tags' => $request->tags,
                        'error' => $e->getMessage()
                    ]);
                    // Continue with update, just log the tag sync failure
                }
            }

            $rack->load(['user:id,name,profile_photo_path', 'tags']);

            return ErrorResponse::success(
                $rack,
                'Rack updated successfully',
                [
                    'new_version' => $rack->version,
                    'updated_fields' => array_keys($updateData),
                    'tags_updated' => $request->has('tags')
                ]
            );
            
        } catch (ValidationException $e) {
            return ErrorResponse::validation($e->errors());
            
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database constraint violations
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return ErrorResponse::create(
                    ErrorCode::DUPLICATE_ENTRY,
                    'A rack with this title already exists'
                );
            }
            
            return ErrorResponse::create(
                ErrorCode::DATABASE_ERROR,
                'Failed to update rack',
                ['rack_id' => $rack->id, 'operation' => 'update'],
                null,
                $e
            );
            
        } catch (\Exception $e) {
            return ErrorResponse::create(
                ErrorCode::TEMPORARY_FAILURE,
                'An unexpected error occurred while updating the rack',
                ['rack_id' => $rack->id, 'operation' => 'update'],
                null,
                $e
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/racks/{id}",
     *     summary="Delete a rack",
     *     description="Delete a rack (owner only)",
     *     operationId="deleteRack",
     *     tags={"Racks"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rack ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Rack deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - not the owner"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Rack not found"
     *     )
     * )
     */
    public function destroy(Rack $rack): JsonResponse
    {
        Gate::authorize('delete', $rack);
        
        $this->rackService->deleteRack($rack);
        
        return response()->json(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/racks/{id}/like",
     *     summary="Toggle like on a rack",
     *     description="Like or unlike a rack",
     *     operationId="toggleLikeRack",
     *     tags={"Racks"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rack ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Like toggled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="liked", type="boolean"),
     *             @OA\Property(property="likes_count", type="integer")
     *         )
     *     )
     * )
     */
    public function toggleLike(Request $request, Rack $rack): JsonResponse
    {
        $user = $request->user();
        $liked = $user->toggleLike($rack);
        
        return response()->json([
            'liked' => $liked,
            'likes_count' => $rack->fresh()->likesCount,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/racks/{id}/download",
     *     summary="Download a rack",
     *     description="Track a rack download and return download URL",
     *     operationId="downloadRack",
     *     tags={"Racks"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rack ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Download initiated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="download_url", type="string"),
     *             @OA\Property(property="filename", type="string")
     *         )
     *     )
     * )
     */
    public function download(Request $request, Rack $rack): JsonResponse
    {
        // Track the download
        $rack->increment('downloads_count');
        $rack->downloads()->create([
            'user_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'download_url' => $rack->download_url,
            'filename' => $rack->original_filename,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/racks/{id}/how-to",
     *     summary="Update rack how-to article",
     *     description="Update the how-to article for a rack (owner only) - supports auto-save",
     *     operationId="updateRackHowTo",
     *     tags={"Racks"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rack ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="how_to_article",
     *                 type="string",
     *                 description="Markdown content for the how-to article",
     *                 example="# How to use this rack\n\nThis rack provides..."
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="How-to article updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="How-to article saved successfully"),
     *             @OA\Property(property="how_to_updated_at", type="string", format="date-time"),
     *             @OA\Property(property="preview", type="string", description="Preview of the article")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - not the owner"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many requests - throttled"
     *     )
     * )
     */
    public function updateHowTo(UpdateHowToRequest $request, Rack $rack): JsonResponse
    {
        Gate::authorize('update', $rack);

        $validated = $request->validated();
        
        $rack->update([
            'how_to_article' => $validated['how_to_article'],
            'how_to_updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'How-to article saved successfully',
            'how_to_updated_at' => $rack->how_to_updated_at,
            'preview' => $rack->how_to_preview,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/racks/{id}/how-to",
     *     summary="Get rack how-to article",
     *     description="Retrieve the how-to article content for a rack",
     *     operationId="getRackHowTo",
     *     tags={"Racks"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rack ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="Response format: raw (markdown) or html",
     *         required=false,
     *         @OA\Schema(type="string", enum={"raw", "html"}, default="raw")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="How-to article retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="how_to_article", type="string", description="Article content"),
     *             @OA\Property(property="how_to_updated_at", type="string", format="date-time"),
     *             @OA\Property(property="format", type="string", enum={"raw", "html"}),
     *             @OA\Property(property="has_content", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Rack not found or no how-to article"
     *     )
     * )
     */
    public function getHowTo(Request $request, Rack $rack): JsonResponse
    {
        // Check if rack is published or user owns it
        if (!$rack->is_public || $rack->status !== 'approved') {
            if (!auth()->check() || $rack->user_id !== auth()->id()) {
                abort(404);
            }
        }

        $format = $request->get('format', 'raw');
        
        if (!$rack->hasHowToArticle()) {
            return response()->json([
                'how_to_article' => null,
                'how_to_updated_at' => null,
                'format' => $format,
                'has_content' => false,
            ]);
        }

        $content = $format === 'html' ? $rack->html_how_to : $rack->how_to_article;

        return response()->json([
            'how_to_article' => $content,
            'how_to_updated_at' => $rack->how_to_updated_at,
            'format' => $format,
            'has_content' => true,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/racks/{id}/how-to",
     *     summary="Delete rack how-to article",
     *     description="Remove the how-to article from a rack (owner only)",
     *     operationId="deleteRackHowTo",
     *     tags={"Racks"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rack ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="How-to article deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="How-to article deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - not the owner"
     *     )
     * )
     */
    public function deleteHowTo(Rack $rack): JsonResponse
    {
        Gate::authorize('update', $rack);

        $rack->update([
            'how_to_article' => null,
            'how_to_updated_at' => null,
        ]);

        return response()->json([
            'message' => 'How-to article deleted successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/racks/{id}/analysis-status",
     *     summary="Get rack enhanced analysis status",
     *     description="Retrieve the enhanced nested chain analysis status for a rack",
     *     operationId="getRackAnalysisStatus",
     *     tags={"Racks"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rack ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Analysis status retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="has_enhanced_analysis", type="boolean"),
     *             @OA\Property(property="constitutional_compliant", type="boolean"),
     *             @OA\Property(property="nested_chains_detected", type="integer"),
     *             @OA\Property(property="max_nesting_depth", type="integer"),
     *             @OA\Property(property="analysis_complete", type="boolean"),
     *             @OA\Property(property="processed_at", type="string", format="date-time"),
     *             @OA\Property(property="compliance_score", type="number"),
     *             @OA\Property(property="requires_reanalysis", type="boolean"),
     *             @OA\Property(property="constitution_version", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Rack not found")
     * )
     */
    public function getAnalysisStatus(Rack $rack): JsonResponse
    {
        // Check if rack is published or user owns it
        if (!$rack->is_public || $rack->status !== 'approved') {
            if (!auth()->check() || $rack->user_id !== auth()->id()) {
                return ErrorResponse::create(
                    ErrorCode::RESOURCE_NOT_FOUND,
                    'This rack is not publicly available'
                );
            }
        }

        $rack->load('enhancedAnalysis');

        if (!$rack->hasEnhancedAnalysis()) {
            return response()->json([
                'has_enhanced_analysis' => false,
                'constitutional_compliant' => null,
                'nested_chains_detected' => 0,
                'max_nesting_depth' => 0,
                'analysis_complete' => false,
                'processed_at' => null,
                'compliance_score' => null,
                'requires_reanalysis' => true,
                'constitution_version' => null,
                'suggestion' => 'Trigger enhanced analysis using POST /racks/{uuid}/analyze-nested-chains'
            ]);
        }

        $analysis = $rack->enhancedAnalysis;
        $complianceStatus = $this->complianceService->getRackComplianceStatus($rack);

        return response()->json([
            'has_enhanced_analysis' => true,
            'constitutional_compliant' => $analysis->constitutional_compliant,
            'nested_chains_detected' => $analysis->nested_chains_detected,
            'max_nesting_depth' => $analysis->max_nesting_depth,
            'analysis_complete' => $analysis->analysis_complete,
            'processed_at' => $analysis->processed_at,
            'compliance_score' => $complianceStatus['compliance_score'] ?? null,
            'requires_reanalysis' => $complianceStatus['requires_revalidation'] ?? false,
            'constitution_version' => $analysis->constitution_version
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/racks/{id}/trigger-analysis",
     *     summary="Trigger enhanced analysis for rack",
     *     description="Manually trigger enhanced nested chain analysis for a rack (owner only)",
     *     operationId="triggerRackAnalysis",
     *     tags={"Racks"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rack ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="force", type="boolean", default=false, description="Force reanalysis even if already completed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Analysis triggered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="analysis_triggered", type="boolean"),
     *             @OA\Property(property="constitutional_compliant", type="boolean"),
     *             @OA\Property(property="nested_chains_detected", type="integer"),
     *             @OA\Property(property="analysis_duration_ms", type="integer"),
     *             @OA\Property(property="processed_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden - not the owner"),
     *     @OA\Response(response=422, description="Analysis failed")
     * )
     */
    public function triggerAnalysis(Request $request, Rack $rack): JsonResponse
    {
        Gate::authorize('update', $rack);

        $request->validate([
            'force' => 'sometimes|boolean'
        ]);

        $force = $request->boolean('force', false);

        try {
            $result = $this->enhancedAnalysisService->analyzeRack($rack, $force);

            if (!$result['analysis_complete']) {
                return ErrorResponse::create(
                    ErrorCode::PROCESSING_FAILED,
                    'Enhanced analysis failed',
                    ['error' => $result['error'] ?? 'Unknown error occurred']
                );
            }

            return response()->json([
                'analysis_triggered' => true,
                'constitutional_compliant' => $result['constitutional_compliant'],
                'nested_chains_detected' => $result['nested_chains_detected'] ?? 0,
                'analysis_duration_ms' => $result['analysis_duration_ms'] ?? 0,
                'processed_at' => $result['processed_at']
            ]);

        } catch (\Exception $e) {
            return ErrorResponse::create(
                ErrorCode::PROCESSING_FAILED,
                'Failed to trigger enhanced analysis',
                ['error' => $e->getMessage()],
                null,
                $e
            );
        }
    }
}
