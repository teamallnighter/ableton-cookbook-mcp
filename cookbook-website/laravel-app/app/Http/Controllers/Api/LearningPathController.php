<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use App\Models\LearningPathStep;
use App\Services\LearningPathService;
use App\Http\Requests\CreateLearningPathRequest;
use App\Http\Requests\UpdateLearningPathRequest;
use App\Http\Resources\LearningPathResource;
use App\Http\Resources\LearningPathListResource;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * @OA\Tag(
 *     name="Learning Paths",
 *     description="Structured educational paths with progress tracking"
 * )
 */
class LearningPathController extends Controller
{
    public function __construct(
        protected LearningPathService $learningPathService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/learning-paths",
     *     summary="List learning paths",
     *     description="Get a paginated list of learning paths with filtering and sorting options",
     *     operationId="getLearningPaths",
     *     tags={"Learning Paths"},
     *     @OA\Parameter(
     *         name="filter[path_type]",
     *         in="query",
     *         description="Filter by path type",
     *         @OA\Schema(type="string", enum={"skill_building", "genre_mastery", "production_workflow", "sound_design", "mixing_mastering", "performance_setup", "custom"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[difficulty_level]",
     *         in="query",
     *         description="Filter by difficulty level",
     *         @OA\Schema(type="string", enum={"beginner", "intermediate", "advanced"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[has_certificate]",
     *         in="query",
     *         description="Filter by paths with certificates",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="filter[is_free]",
     *         in="query",
     *         description="Filter by free paths",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="filter[is_featured]",
     *         in="query",
     *         description="Filter by featured paths",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Full-text search in title and description",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort learning paths",
     *         @OA\Schema(type="string", enum={"created_at", "-created_at", "average_rating", "-average_rating", "enrollments_count", "-enrollments_count", "completion_rate", "-completion_rate"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/LearningPathListResource")
     *             ),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $learningPaths = QueryBuilder::for(LearningPath::class)
            ->published()
            ->with(['user:id,name,avatar_path'])
            ->allowedFilters([
                'path_type',
                'difficulty_level',
                'has_certificate',
                'is_free',
                'is_featured',
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->whereFullText(['title', 'description'], $value);
                }),
                AllowedFilter::callback('user', function ($query, $value) {
                    $query->where('user_id', $value);
                }),
            ])
            ->allowedSorts([
                'created_at',
                'published_at',
                'average_rating',
                'enrollments_count',
                'completions_count',
                'completion_rate',
                'title',
            ])
            ->defaultSort('-is_featured', '-average_rating')
            ->jsonPaginate();

        return LearningPathListResource::collection($learningPaths);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/learning-paths",
     *     summary="Create a new learning path",
     *     description="Create a new learning path for the authenticated user",
     *     operationId="createLearningPath",
     *     tags={"Learning Paths"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=255, example="Mastering Progressive House"),
     *             @OA\Property(property="description", type="string", example="Complete learning path for progressive house production"),
     *             @OA\Property(property="path_type", type="string", enum={"skill_building", "genre_mastery", "production_workflow", "sound_design", "mixing_mastering", "performance_setup", "custom"}),
     *             @OA\Property(property="difficulty_level", type="string", enum={"beginner", "intermediate", "advanced"}),
     *             @OA\Property(property="estimated_total_time", type="number", format="float", example=25.5),
     *             @OA\Property(property="has_certificate", type="boolean", example=true),
     *             @OA\Property(property="passing_score", type="number", format="float", example=70.0),
     *             @OA\Property(property="prerequisites", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="learning_objectives", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="skills_taught", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="is_free", type="boolean", example=true),
     *             @OA\Property(property="path_price", type="number", format="float", example=49.99)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Learning path created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/LearningPath")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(CreateLearningPathRequest $request)
    {
        $learningPath = $this->learningPathService->createLearningPath(
            $request->user(),
            $request->validated()
        );

        return new LearningPathResource($learningPath);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/learning-paths/{id}",
     *     summary="Get learning path details",
     *     description="Retrieve detailed information about a specific learning path",
     *     operationId="getLearningPath",
     *     tags={"Learning Paths"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Learning path ID or UUID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include related data (steps,user,progress)",
     *         @OA\Schema(type="string", example="steps,user,progress")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/LearningPath")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Learning path not found")
     * )
     */
    public function show(Request $request, string $id)
    {
        $learningPath = $this->findLearningPath($id);

        // Handle includes
        $includes = $request->get('include', '');
        $allowedIncludes = ['steps', 'user', 'progress'];
        $requestedIncludes = array_intersect(explode(',', $includes), $allowedIncludes);

        if (!empty($requestedIncludes)) {
            $learningPath->load($requestedIncludes);
        }

        // Load user progress if authenticated and requested
        if ($request->user() && in_array('progress', $requestedIncludes)) {
            $learningPath->user_progress = $learningPath->getUserProgress($request->user());
        }

        return new LearningPathResource($learningPath);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/learning-paths/{id}",
     *     summary="Update learning path",
     *     description="Update an existing learning path (owner only)",
     *     operationId="updateLearningPath",
     *     tags={"Learning Paths"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Learning path ID or UUID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=255),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="how_to_article", type="string"),
     *             @OA\Property(property="difficulty_level", type="string", enum={"beginner", "intermediate", "advanced"}),
     *             @OA\Property(property="estimated_total_time", type="number", format="float"),
     *             @OA\Property(property="prerequisites", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="learning_objectives", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="is_free", type="boolean"),
     *             @OA\Property(property="path_price", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Learning path updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/LearningPath")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Not the owner"),
     *     @OA\Response(response=404, description="Learning path not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateLearningPathRequest $request, string $id)
    {
        $learningPath = $this->findLearningPath($id);
        
        $this->authorize('update', $learningPath);

        $learningPath->update($request->validated());

        return new LearningPathResource($learningPath->fresh());
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/learning-paths/{id}",
     *     summary="Delete learning path",
     *     description="Delete a learning path (owner only)",
     *     operationId="deleteLearningPath",
     *     tags={"Learning Paths"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Learning path ID or UUID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=204, description="Learning path deleted successfully"),
     *     @OA\Response(response=403, description="Forbidden - Not the owner"),
     *     @OA\Response(response=404, description="Learning path not found")
     * )
     */
    public function destroy(Request $request, string $id)
    {
        $learningPath = $this->findLearningPath($id);
        
        $this->authorize('delete', $learningPath);

        $learningPath->delete();

        return response()->noContent();
    }

    /**
     * @OA\Post(
     *     path="/api/v1/learning-paths/{id}/enroll",
     *     summary="Enroll in learning path",
     *     description="Enroll the authenticated user in a learning path",
     *     operationId="enrollLearningPath",
     *     tags={"Learning Paths"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Learning path ID or UUID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Enrolled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/UserProgress"),
     *             @OA\Property(property="message", type="string", example="Successfully enrolled in learning path")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Prerequisites not met or already enrolled"),
     *     @OA\Response(response=404, description="Learning path not found")
     * )
     */
    public function enroll(Request $request, string $id)
    {
        $learningPath = $this->findLearningPath($id);

        try {
            $progress = $this->learningPathService->enrollUser($learningPath, $request->user());

            return response()->json([
                'data' => $progress->getProgressSummary(),
                'message' => 'Successfully enrolled in learning path',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/learning-paths/{id}/steps/{stepId}/complete",
     *     summary="Complete learning path step",
     *     description="Mark a learning path step as completed",
     *     operationId="completeLearningPathStep",
     *     tags={"Learning Paths"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Learning path ID or UUID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="stepId",
     *         in="path",
     *         required=true,
     *         description="Learning path step ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="time_spent", type="number", format="float", example=2.5),
     *             @OA\Property(property="notes", type="string", description="User's completion notes"),
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5, description="Step rating")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Step completed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Step completed successfully"),
     *             @OA\Property(property="progress", ref="#/components/schemas/UserProgress"),
     *             @OA\Property(property="next_step", ref="#/components/schemas/LearningPathStep")
     *         )
     *     ),
     *     @OA\Response(response=400, description="User not enrolled or step already completed"),
     *     @OA\Response(response=404, description="Learning path or step not found")
     * )
     */
    public function completeStep(Request $request, string $id, int $stepId)
    {
        $learningPath = $this->findLearningPath($id);
        $step = $learningPath->steps()->findOrFail($stepId);

        $request->validate([
            'time_spent' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);

        try {
            $this->learningPathService->completeStep(
                $step,
                $request->user(),
                $request->only(['time_spent', 'notes', 'rating'])
            );

            $progress = $learningPath->getUserProgress($request->user());
            $nextStep = $learningPath->getNextStepFor($request->user());

            return response()->json([
                'message' => 'Step completed successfully',
                'progress' => $progress->getProgressSummary(),
                'next_step' => $nextStep,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/learning-paths/{id}/steps/{stepId}/assess",
     *     summary="Submit assessment for learning path step",
     *     description="Submit answers for an assessment step",
     *     operationId="assessLearningPathStep",
     *     tags={"Learning Paths"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Learning path ID or UUID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="stepId",
     *         in="path",
     *         required=true,
     *         description="Learning path step ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="answers", type="object", description="Assessment answers as key-value pairs"),
     *             @OA\Property(property="time_spent", type="number", format="float", example=1.5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Assessment submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="score", type="number", format="float", example=85.0),
     *             @OA\Property(property="passed", type="boolean", example=true),
     *             @OA\Property(property="passing_score", type="number", format="float", example=70.0),
     *             @OA\Property(property="attempts_used", type="integer", example=1),
     *             @OA\Property(property="can_retake", type="boolean", example=true),
     *             @OA\Property(property="feedback", type="string", description="Assessment feedback")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid assessment data"),
     *     @OA\Response(response=404, description="Learning path or step not found")
     * )
     */
    public function assessStep(Request $request, string $id, int $stepId)
    {
        $learningPath = $this->findLearningPath($id);
        $step = $learningPath->steps()->findOrFail($stepId);

        if (!$step->is_assessment) {
            return response()->json(['error' => 'This step is not an assessment'], 400);
        }

        $request->validate([
            'answers' => 'required|array',
            'time_spent' => 'nullable|numeric|min:0',
        ]);

        try {
            // Calculate score (this would be more complex in real implementation)
            $score = $this->calculateAssessmentScore($step, $request->answers);

            $result = $this->learningPathService->recordAssessmentAttempt(
                $step,
                $request->user(),
                $score,
                $request->answers
            );

            return response()->json(array_merge($result, [
                'feedback' => $this->generateAssessmentFeedback($step, $score),
            ]));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/learning-paths/{id}/progress",
     *     summary="Get user progress for learning path",
     *     description="Get detailed progress information for authenticated user",
     *     operationId="getLearningPathProgress",
     *     tags={"Learning Paths"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Learning path ID or UUID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Progress retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/UserProgress")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Learning path not found or user not enrolled")
     * )
     */
    public function getProgress(Request $request, string $id)
    {
        $learningPath = $this->findLearningPath($id);
        $progress = $learningPath->getUserProgress($request->user());

        if (!$progress) {
            return response()->json(['error' => 'Not enrolled in this learning path'], 404);
        }

        return response()->json(['data' => $progress->getProgressSummary()]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/learning-paths/featured",
     *     summary="Get featured learning paths",
     *     description="Get a list of featured learning paths",
     *     operationId="getFeaturedLearningPaths",
     *     tags={"Learning Paths"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of paths to return",
     *         @OA\Schema(type="integer", minimum=1, maximum=50, default=8)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/LearningPathListResource")
     *             )
     *         )
     *     )
     * )
     */
    public function featured(Request $request)
    {
        $limit = $request->integer('limit', 8);
        $limit = max(1, min(50, $limit)); // Clamp between 1-50

        $featuredPaths = $this->learningPathService->getFeaturedPaths($limit);

        return LearningPathListResource::collection($featuredPaths);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/learning-paths/recommended",
     *     summary="Get recommended learning paths for user",
     *     description="Get personalized learning path recommendations for authenticated user",
     *     operationId="getRecommendedLearningPaths",
     *     tags={"Learning Paths"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of paths to return",
     *         @OA\Schema(type="integer", minimum=1, maximum=20, default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/LearningPathListResource")
     *             )
     *         )
     *     )
     * )
     */
    public function recommended(Request $request)
    {
        $limit = $request->integer('limit', 10);
        $limit = max(1, min(20, $limit)); // Clamp between 1-20

        $recommendedPaths = $this->learningPathService->getRecommendedPaths(
            $request->user(),
            $limit
        );

        return LearningPathListResource::collection($recommendedPaths);
    }

    /**
     * Find learning path by ID or UUID
     */
    protected function findLearningPath(string $id): LearningPath
    {
        return LearningPath::where('id', $id)
            ->orWhere('uuid', $id)
            ->published()
            ->firstOrFail();
    }

    /**
     * Calculate assessment score (simplified implementation)
     */
    protected function calculateAssessmentScore(LearningPathStep $step, array $answers): float
    {
        // This would be more sophisticated in a real implementation
        // For now, just return a simple score based on answer count
        return min(100, count($answers) * 20);
    }

    /**
     * Generate assessment feedback
     */
    protected function generateAssessmentFeedback(LearningPathStep $step, float $score): string
    {
        if ($score >= 90) {
            return 'Excellent work! You have a strong understanding of the material.';
        } elseif ($score >= 70) {
            return 'Good job! You passed the assessment. Consider reviewing areas where you struggled.';
        } else {
            return 'You need more practice. Review the material and try again when ready.';
        }
    }
}