<?php

namespace App\Http\Controllers;

use App\Enums\ErrorCode;
use App\Http\Responses\ErrorResponse;
use App\Models\Rack;
use App\Models\Tag;
use App\Services\MarkdownService;
use App\Services\AutoSaveService;
use App\Services\ConflictResolutionService;
use App\Exceptions\ConcurrencyConflictException;
use App\Exceptions\AutoSaveException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RackMetadataController extends Controller
{
    public function __construct(
        private MarkdownService $markdownService,
        private AutoSaveService $autoSaveService,
        private ConflictResolutionService $conflictService
    ) {
    }

    /**
     * Step 2: Show metadata form (immediately after upload, during analysis)
     */
    public function create(Rack $rack)
    {
        // Ensure user owns this rack
        if ($rack->user_id !== auth()->id()) {
            abort(403);
        }

        // If rack is already published, redirect to regular edit
        if ($rack->status === 'approved') {
            return redirect()->route('racks.edit', $rack);
        }

        // Get dynamic categories based on rack type (use detected or default)
        $categories = $this->getCategoriesByRackType($rack->rack_type);
        
        return view('racks.metadata', compact('rack', 'categories'));
    }

    /**
     * Save metadata (can be called multiple times during analysis)
     */
    public function store(Request $request, Rack $rack)
    {
        // Ensure user owns this rack
        if ($rack->user_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'category' => 'required|string|max:100',
            'tags' => 'nullable|string|max:500',
            'how_to_article' => 'nullable|string|max:10000',
            'is_public' => 'boolean'
        ]);

        // Update rack with metadata
        $rack->update([
            'title' => $request->title,
            'description' => $request->description,
            'category' => $request->category,
            'how_to_article' => $request->how_to_article,
            'is_public' => $request->boolean('is_public', true),
        ]);

        // Handle tags
        if ($request->tags) {
            $rack->tags()->detach(); // Clear existing tags
            $this->attachTags($rack, $request->tags);
        }

        // If analysis is complete, redirect to annotation
        if ($this->isAnalysisComplete($rack)) {
            return redirect()->route('racks.annotate', $rack)
                ->with('success', 'Metadata saved! Now you can annotate your rack chains.');
        }

        // If analysis is still processing, stay on metadata page
        return redirect()->route('racks.metadata', $rack)
            ->with('success', 'Metadata saved! Analysis is still in progress...');
    }

    /**
     * Auto-save metadata via AJAX with robust concurrency control
     */
    public function autoSave(Request $request, Rack $rack): JsonResponse
    {
        // Ensure user owns this rack
        if ($rack->user_id !== auth()->id()) {
            return ErrorResponse::create(
                ErrorCode::UNAUTHORIZED,
                'You do not have permission to edit this rack',
                ['rack_id' => $rack->id, 'user_id' => auth()->id()]
            );
        }

        try {
            // Validate input
            $validated = $request->validate([
                'field' => 'required|string|in:title,description,category,tags,how_to_article',
                'value' => 'nullable|string|max:10000',
                'version' => 'nullable|integer',
                'session_id' => 'nullable|string|max:100'
            ]);

            // Show loading state for long operations
            if (strlen($validated['value'] ?? '') > 5000) {
                return ErrorResponse::loading('Saving large content...', [
                    'field' => $validated['field'],
                    'content_size' => strlen($validated['value'] ?? ''),
                    'estimated_time' => '2-3 seconds'
                ]);
            }

            // Use the AutoSaveService for robust saving
            $result = $this->autoSaveService->saveField(
                $rack,
                $validated['field'],
                $validated['value'],
                [
                    'version' => $validated['version'] ?? null,
                    'session_id' => $validated['session_id'] ?? null
                ]
            );

            // Return enhanced success response
            return ErrorResponse::success(
                $result,
                'Auto-saved successfully',
                [
                    'field' => $validated['field'],
                    'save_time' => now()->toISOString(),
                    'version' => $result['version'] ?? null,
                    'conflicts_detected' => $result['has_conflicts'] ?? false
                ]
            );

        } catch (ValidationException $e) {
            return ErrorResponse::validation(
                $e->errors(),
                'Invalid auto-save data'
            );

        } catch (ConcurrencyConflictException $e) {
            return ErrorResponse::create(
                ErrorCode::AUTOSAVE_CONFLICT,
                $e->getMessage(),
                [
                    'rack_id' => $rack->id,
                    'field' => $request->field,
                    'conflict_type' => $e->getConflictType(),
                    'conflicting_session' => $e->getConflictingSession()
                ],
                [
                    'server_value' => $e->getServerValue(),
                    'your_value' => $e->getClientValue(),
                    'resolution_options' => ['keep_yours', 'keep_server', 'merge']
                ],
                $e
            );

        } catch (AutoSaveException $e) {
            Log::warning('Auto-save failed', [
                'rack_id' => $rack->id,
                'field' => $request->field,
                'error' => $e->getMessage(),
                'error_type' => $e->getErrorType()
            ]);

            $errorCode = match($e->getErrorType()) {
                'version_conflict' => ErrorCode::VERSION_MISMATCH,
                'lock_timeout' => ErrorCode::LOCK_TIMEOUT,
                'network_error' => ErrorCode::NETWORK_ERROR,
                'database_error' => ErrorCode::DATABASE_ERROR,
                default => ErrorCode::TEMPORARY_FAILURE
            };

            return ErrorResponse::create(
                $errorCode,
                $e->getMessage(),
                [
                    'rack_id' => $rack->id,
                    'field' => $request->field,
                    'auto_save_error_type' => $e->getErrorType()
                ],
                null,
                $e
            );

        } catch (\Exception $e) {
            Log::error('Unexpected auto-save error', [
                'rack_id' => $rack->id,
                'field' => $request->field,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResponse::create(
                ErrorCode::TEMPORARY_FAILURE,
                'An unexpected error occurred during auto-save',
                [
                    'rack_id' => $rack->id,
                    'field' => $request->field,
                    'operation' => 'auto_save'
                ],
                null,
                $e
            );
        }
    }

    /**
     * Get real-time analysis status and auto-save state via AJAX
     */
    public function status(Rack $rack): JsonResponse
    {
        try {
            // Ensure user owns this rack
            if ($rack->user_id !== auth()->id()) {
                return ErrorResponse::create(
                    ErrorCode::UNAUTHORIZED,
                    'You do not have permission to view this rack status',
                    ['rack_id' => $rack->id]
                );
            }

            // Refresh rack data from database with error handling
            try {
                $rack->refresh();
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return ErrorResponse::create(
                    ErrorCode::RESOURCE_DELETED,
                    'This rack has been deleted',
                    ['rack_id' => $rack->id]
                );
            }

            $sessionId = request('session_id');
            $currentState = null;
            
            // Get current state with error handling
            if ($sessionId) {
                try {
                    $currentState = $this->autoSaveService->getCurrentState($rack, $sessionId);
                } catch (\Exception $e) {
                    Log::warning('Failed to get auto-save state', [
                        'rack_id' => $rack->id,
                        'session_id' => $sessionId,
                        'error' => $e->getMessage()
                    ]);
                    // Continue without auto-save state rather than failing
                }
            }

            // Get enhanced processing status
            $processingStatus = $this->getEnhancedProcessingStatus($rack);
            
            // Determine if status indicates loading/processing
            $isLoading = $processingStatus['is_processing'] ?? false;
            $hasError = in_array($rack->processing_status ?? $rack->status, ['failed', 'permanently_failed']);

            $responseData = [
                'status' => $rack->status,
                'processing_status' => $processingStatus,
                'is_complete' => $this->isAnalysisComplete($rack),
                'is_loading' => $isLoading,
                'has_error' => $hasError,
                'error_message' => $rack->processing_error,
                'can_retry' => $this->canRetryProcessing($rack),
                'last_updated' => $rack->updated_at->toISOString(),
                'analysis_data' => $this->isAnalysisComplete($rack) ? [
                    'rack_type' => $rack->rack_type,
                    'device_count' => $rack->device_count,
                    'chain_count' => $rack->chain_count,
                    'category' => $rack->category,
                    'ableton_version' => $rack->ableton_version,
                    'ableton_edition' => $rack->ableton_edition,
                ] : null,
                'auto_save_state' => $currentState ? [
                    'version' => $currentState['version'],
                    'last_modified' => $currentState['last_modified'],
                    'active_sessions' => count($currentState['active_sessions']),
                    'has_conflicts' => !empty($currentState['pending_conflicts']),
                    'session_id' => $sessionId
                ] : null
            ];

            // Return appropriate response type based on status
            if ($isLoading) {
                return ErrorResponse::loading(
                    $processingStatus['label'] ?? 'Processing...',
                    [
                        'progress_percentage' => $processingStatus['progress_percentage'] ?? 0,
                        'estimated_completion' => $this->estimateCompletionTime($rack),
                        'current_stage' => $rack->processing_stage
                    ]
                );
            }

            return ErrorResponse::success(
                $responseData,
                null,
                [
                    'polling_interval' => $isLoading ? 2000 : 10000, // Poll more frequently when processing
                    'next_poll_at' => now()->addSeconds($isLoading ? 2 : 10)->toISOString()
                ]
            );
            
        } catch (\Exception $e) {
            return ErrorResponse::create(
                ErrorCode::TEMPORARY_FAILURE,
                'Failed to get rack status',
                ['rack_id' => $rack->id, 'operation' => 'status'],
                null,
                $e
            );
        }
    }

    /**
     * Get detailed job progress for the rack
     */
    public function progress(Rack $rack): JsonResponse
    {
        // Ensure user owns this rack
        if ($rack->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            // Get progress tracking service if available
            if (app()->bound(RackProcessingProgress::class)) {
                $progressService = app(RackProcessingProgress::class);
                $jobId = $rack->current_job_id;
                
                if ($jobId) {
                    $progress = $progressService->getProgress($jobId);
                    $estimatedCompletion = $progressService->estimateCompletionTime($jobId);
                    $isStalled = $progressService->isProgressStalled($jobId);
                    
                    return response()->json([
                        'has_progress' => true,
                        'job_id' => $jobId,
                        'progress' => $progress,
                        'estimated_completion' => $estimatedCompletion?->toISOString(),
                        'is_stalled' => $isStalled,
                        'history' => $progressService->getProgressHistory($jobId)
                    ]);
                }
            }
            
            // Fall back to basic status
            return response()->json([
                'has_progress' => false,
                'status' => $rack->processing_status ?? $rack->status,
                'progress_percentage' => $rack->processing_progress ?? 0,
                'stage' => $rack->processing_stage,
                'message' => $rack->processing_message
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get rack progress', [
                'rack_id' => $rack->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'has_progress' => false,
                'error' => 'Failed to retrieve progress information'
            ], 500);
        }
    }

    /**
     * Manually retry failed rack processing
     */
    public function retry(Rack $rack): JsonResponse
    {
        try {
            // Ensure user owns this rack
            if ($rack->user_id !== auth()->id()) {
                return ErrorResponse::create(
                    ErrorCode::UNAUTHORIZED,
                    'You do not have permission to retry processing for this rack',
                    ['rack_id' => $rack->id]
                );
            }

            // Check if retry is allowed
            if (!$this->canRetryProcessing($rack)) {
                $reason = $this->getRetryBlockedReason($rack);
                
                return ErrorResponse::create(
                    ErrorCode::UNSUPPORTED_OPERATION,
                    'This rack cannot be retried at this time',
                    [
                        'rack_id' => $rack->id,
                        'current_status' => $rack->processing_status ?? $rack->status,
                        'retry_count' => $rack->retry_count ?? 0,
                        'reason' => $reason
                    ]
                );
            }

            // Check retry limits
            $retryCount = $rack->retry_count ?? 0;
            if ($retryCount >= 5) {
                return ErrorResponse::create(
                    ErrorCode::JOB_RETRY_LIMIT_EXCEEDED,
                    'Maximum retry attempts exceeded',
                    [
                        'rack_id' => $rack->id,
                        'retry_count' => $retryCount,
                        'max_retries' => 5
                    ]
                );
            }

            // Check if job queue is available
            try {
                // Test job queue availability
                $queueConnection = config('queue.default');
                if ($queueConnection === 'database') {
                    $pendingJobs = \Illuminate\Support\Facades\DB::table('jobs')
                        ->where('queue', 'processing')
                        ->count();
                    
                    if ($pendingJobs > 100) { // Configurable threshold
                        return ErrorResponse::create(
                            ErrorCode::JOB_QUEUE_FULL,
                            'Processing queue is currently full',
                            [
                                'pending_jobs' => $pendingJobs,
                                'estimated_wait' => '5-10 minutes'
                            ]
                        );
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to check job queue status', ['error' => $e->getMessage()]);
            }

            // Dispatch new processing job
            \App\Jobs\ProcessRackFileJob::dispatch($rack);

            // Update rack status
            $newRetryCount = $retryCount + 1;
            $rack->update([
                'processing_status' => 'queued',
                'processing_error' => null,
                'processing_progress' => 0,
                'processing_stage' => 'queued',
                'retry_count' => $newRetryCount,
                'last_retry_at' => now()
            ]);

            Log::info('Manual retry initiated for rack', [
                'rack_id' => $rack->id,
                'user_id' => $rack->user_id,
                'retry_count' => $newRetryCount,
                'previous_error' => $rack->getOriginal('processing_error')
            ]);

            return ErrorResponse::success(
                [
                    'status' => 'queued',
                    'retry_count' => $newRetryCount,
                    'estimated_completion' => now()->addMinutes(2)->toISOString()
                ],
                'Processing retry initiated successfully',
                [
                    'queue_position' => 'Processing will begin shortly',
                    'max_retries_remaining' => 5 - $newRetryCount,
                    'can_cancel' => true
                ]
            );

        } catch (\Illuminate\Queue\InvalidPayloadException $e) {
            return ErrorResponse::create(
                ErrorCode::JOB_FAILED,
                'Failed to queue retry job',
                ['rack_id' => $rack->id],
                null,
                $e
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to retry rack processing', [
                'rack_id' => $rack->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResponse::create(
                ErrorCode::TEMPORARY_FAILURE,
                'Failed to initiate retry. Please try again in a few moments.',
                ['rack_id' => $rack->id, 'operation' => 'retry'],
                null,
                $e
            );
        }
    }

    /**
     * Get job execution details for the rack
     */
    public function jobDetails(Rack $rack): JsonResponse
    {
        // Ensure user owns this rack
        if ($rack->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $jobExecutions = \App\Models\JobExecution::where('model_id', $rack->id)
                ->where('model_type', \App\Models\Rack::class)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $jobDetails = $jobExecutions->map(function ($job) {
                return [
                    'id' => $job->job_id,
                    'status' => $job->status,
                    'attempts' => $job->attempts,
                    'started_at' => $job->started_at?->toISOString(),
                    'completed_at' => $job->completed_at?->toISOString(),
                    'failed_at' => $job->failed_at?->toISOString(),
                    'execution_time' => $job->getFormattedExecutionTime(),
                    'memory_usage' => $job->getFormattedMemoryUsage(),
                    'failure_category' => $job->failure_category,
                    'failure_reason' => $job->failure_reason,
                    'next_retry_at' => $job->next_retry_at?->toISOString(),
                ];
            });

            return response()->json([
                'job_executions' => $jobDetails,
                'current_job_id' => $rack->current_job_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get job details', [
                'rack_id' => $rack->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to retrieve job details'
            ], 500);
        }
    }

    /**
     * Preview how-to article markdown
     */
    public function previewHowTo(Request $request, Rack $rack): JsonResponse
    {
        // Ensure user owns this rack
        if ($rack->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'markdown' => 'required|string|max:10000'
        ]);

        $html = $this->markdownService->parseToHtml($request->markdown);

        return response()->json([
            'html' => $html
        ]);
    }

    /**
     * Proceed to annotation (only when analysis is complete)
     */
    public function proceedToAnnotation(Rack $rack)
    {
        // Ensure user owns this rack
        if ($rack->user_id !== auth()->id()) {
            abort(403);
        }

        // Check if analysis is complete
        if (!$this->isAnalysisComplete($rack)) {
            return redirect()->route('racks.metadata', $rack)
                ->withErrors(['analysis' => 'Please wait for analysis to complete before proceeding.']);
        }

        return redirect()->route('racks.annotate', $rack);
    }

    /**
     * Quick publish (skip annotation step)
     */
    public function quickPublish(Rack $rack)
    {
        // Ensure user owns this rack
        if ($rack->user_id !== auth()->id()) {
            abort(403);
        }

        // Check if analysis is complete
        if (!$this->isAnalysisComplete($rack)) {
            return redirect()->route('racks.metadata', $rack)
                ->withErrors(['analysis' => 'Please wait for analysis to complete before publishing.']);
        }

        // Validate required metadata exists
        if (empty($rack->title) || empty($rack->description)) {
            return redirect()->route('racks.metadata', $rack)
                ->withErrors(['metadata' => 'Please fill in title and description before publishing.']);
        }

        // Publish the rack
        $rack->update([
            'status' => 'approved',
            'published_at' => now()
        ]);

        return redirect()->route('racks.show', $rack)
            ->with('success', 'Your rack has been published successfully!');
    }

    /**
     * Get current conflicts for resolution
     */
    public function getConflicts(Request $request, Rack $rack): JsonResponse
    {
        // Ensure user owns this rack
        if ($rack->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $sessionId = $request->get('session_id');
        if (!$sessionId) {
            return response()->json([
                'has_conflicts' => false,
                'error' => 'Session ID required'
            ], 400);
        }

        try {
            $conflicts = $this->conflictService->presentConflictsForResolution($rack, $sessionId);
            return response()->json($conflicts);

        } catch (\Exception $e) {
            Log::error('Failed to get conflicts', [
                'rack_id' => $rack->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'has_conflicts' => false,
                'error' => 'Failed to retrieve conflicts'
            ], 500);
        }
    }

    /**
     * Resolve conflicts with user choices
     */
    public function resolveConflicts(Request $request, Rack $rack): JsonResponse
    {
        // Ensure user owns this rack
        if ($rack->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $validated = $request->validate([
                'session_id' => 'required|string|max:100',
                'resolutions' => 'required|array',
                'resolutions.*' => 'required|string|in:keep_yours,keep_server,merge'
            ]);

            $result = $this->conflictService->resolveConflicts(
                $rack,
                $validated['session_id'],
                $validated['resolutions']
            );

            return response()->json($result);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid resolution data',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to resolve conflicts', [
                'rack_id' => $rack->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to resolve conflicts'
            ], 500);
        }
    }

    /**
     * Auto-resolve conflicts where possible
     */
    public function autoResolveConflicts(Request $request, Rack $rack): JsonResponse
    {
        // Ensure user owns this rack
        if ($rack->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $validated = $request->validate([
                'session_id' => 'required|string|max:100',
                'strategy' => 'nullable|string|in:last_write_wins,first_write_wins,smart_merge'
            ]);

            $result = $this->conflictService->autoResolveConflicts(
                $rack,
                $validated['session_id'],
                $validated['strategy'] ?? 'smart_merge'
            );

            return response()->json($result);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid auto-resolve data',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to auto-resolve conflicts', [
                'rack_id' => $rack->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to auto-resolve conflicts'
            ], 500);
        }
    }

    /**
     * Handle connection recovery after network issues
     */
    public function handleConnectionRecovery(Request $request, Rack $rack): JsonResponse
    {
        // Ensure user owns this rack
        if ($rack->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $validated = $request->validate([
                'session_id' => 'required|string|max:100',
                'client_state' => 'required|array',
                'client_state.version' => 'nullable|integer',
                'client_state.last_sync' => 'nullable|string',
                'client_state.pending_changes' => 'nullable|array'
            ]);

            $result = $this->autoSaveService->handleConnectionRecovery(
                $rack,
                $validated['session_id'],
                $validated['client_state']
            );

            return response()->json($result);

        } catch (ValidationException $e) {
            return response()->json([
                'recovery_needed' => false,
                'error' => 'Invalid recovery data',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to handle connection recovery', [
                'rack_id' => $rack->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'recovery_needed' => false,
                'error' => 'Failed to handle recovery'
            ], 500);
        }
    }

    /**
     * Get categories based on rack type
     */
    private function getCategoriesByRackType(?string $rackType): array
    {
        return match($rackType) {
            'AudioEffectGroupDevice' => [
                'dynamics' => 'Dynamics',
                'time-based' => 'Time Based', 
                'modulation' => 'Modulation',
                'spectral' => 'Spectral',
                'filters' => 'Filters',
                'creative-effects' => 'Creative Effects',
                'utility' => 'Utility',
                'mixing' => 'Mixing',
                'distortion' => 'Distortion'
            ],
            'InstrumentGroupDevice' => [
                'drums' => 'Drums',
                'samplers' => 'Samplers',
                'synths' => 'Synths',
                'bass' => 'Bass',
                'fx' => 'FX'
            ],
            'MidiEffectGroupDevice' => [
                'arpeggiators-sequencers' => 'Arpeggiators & Sequencers',
                'music-theory' => 'Music Theory',
                'other' => 'Other'
            ],
            default => [
                'effects' => 'Effects',
                'instruments' => 'Instruments',
                'midi-effects' => 'MIDI Effects',
                'other' => 'Other'
            ]
        };
    }

    /**
     * Attach tags to rack
     */
    private function attachTags(Rack $rack, string $tagString): void
    {
        $tagNames = array_filter(array_map('trim', explode(',', $tagString)));
        
        foreach ($tagNames as $tagName) {
            if (strlen($tagName) > 2) { // Minimum tag length
                $tag = Tag::firstOrCreate([
                    'name' => $tagName,
                    'slug' => Str::slug($tagName)
                ]);
                
                $rack->tags()->attach($tag->id);
            }
        }
    }

    /**
     * Check if analysis is complete
     */
    private function isAnalysisComplete(Rack $rack): bool
    {
        $status = $rack->processing_status ?? $rack->status;
        return in_array($status, ['analysis_complete', 'ready_for_annotation', 'pending', 'approved']);
    }
    
    /**
     * Get enhanced processing status information
     */
    private function getEnhancedProcessingStatus(Rack $rack): array
    {
        $processingStatus = $rack->processing_status ?? $rack->status;
        
        try {
            if (class_exists(\App\Enums\RackProcessingStatus::class)) {
                $statusEnum = \App\Enums\RackProcessingStatus::from($processingStatus);
                return [
                    'value' => $statusEnum->value,
                    'label' => $statusEnum->label(),
                    'description' => $statusEnum->description(),
                    'progress_percentage' => $statusEnum->progressPercentage(),
                    'is_processing' => $statusEnum->isProcessing(),
                    'is_failure' => $statusEnum->isFailure(),
                    'is_complete' => $statusEnum->isComplete(),
                    'css_class' => $statusEnum->cssClass(),
                    'icon' => $statusEnum->icon()
                ];
            }
        } catch (\Exception $e) {
            // Fall back to basic status if enum not available
        }
        
        return [
            'value' => $processingStatus,
            'label' => ucfirst(str_replace('_', ' ', $processingStatus)),
            'progress_percentage' => $rack->processing_progress ?? 0,
            'is_processing' => in_array($processingStatus, ['processing', 'analyzing', 'queued']),
            'is_failure' => in_array($processingStatus, ['failed', 'permanently_failed']),
            'is_complete' => in_array($processingStatus, ['pending', 'approved'])
        ];
    }
    
    /**
     * Check if processing can be retried for this rack
     */
    private function canRetryProcessing(Rack $rack): bool
    {
        $status = $rack->processing_status ?? $rack->status;
        $retryCount = $rack->retry_count ?? 0;
        
        // Allow retry for failed statuses, but limit retry attempts
        if (in_array($status, ['failed', 'permanently_failed'])) {
            return $retryCount < 5; // Maximum 5 manual retries
        }
        
        // Allow retry for stalled processing (older than 15 minutes)
        if (in_array($status, ['processing', 'analyzing']) && $rack->updated_at) {
            $minutesSinceUpdate = $rack->updated_at->diffInMinutes(now());
            return $minutesSinceUpdate > 15;
        }
        
        return false;
    }
    
    /**
     * Get reason why retry is blocked
     */
    private function getRetryBlockedReason(Rack $rack): string
    {
        $status = $rack->processing_status ?? $rack->status;
        $retryCount = $rack->retry_count ?? 0;
        
        if ($retryCount >= 5) {
            return 'Maximum retry attempts exceeded';
        }
        
        if (in_array($status, ['processing', 'analyzing', 'queued'])) {
            return 'Processing is currently in progress';
        }
        
        if (in_array($status, ['approved', 'pending'])) {
            return 'Processing has already completed successfully';
        }
        
        return 'Current status does not allow retry';
    }
    
    /**
     * Estimate completion time for processing
     */
    private function estimateCompletionTime(Rack $rack): ?string
    {
        $status = $rack->processing_status ?? $rack->status;
        
        if (!in_array($status, ['processing', 'analyzing', 'queued'])) {
            return null;
        }
        
        // Base estimates on file size and complexity
        $fileSize = $rack->file_size ?? 0;
        $estimatedMinutes = match(true) {
            $fileSize < 100 * 1024 => 1, // < 100KB
            $fileSize < 500 * 1024 => 2, // < 500KB
            $fileSize < 1024 * 1024 => 3, // < 1MB
            default => 5
        };
        
        // Adjust for current queue load (simplified)
        if ($status === 'queued') {
            $estimatedMinutes += 1;
        }
        
        return now()->addMinutes($estimatedMinutes)->toISOString();
    }
}