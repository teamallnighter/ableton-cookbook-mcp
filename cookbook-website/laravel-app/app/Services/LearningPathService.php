<?php

namespace App\Services;

use App\Models\LearningPath;
use App\Models\LearningPathStep;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Service for managing learning paths and user progress
 */
class LearningPathService
{
    public function __construct(
        protected CollectionAnalyticsService $analyticsService,
        protected CertificateService $certificateService
    ) {}

    /**
     * Create a new learning path
     */
    public function createLearningPath(User $user, array $data): LearningPath
    {
        return DB::transaction(function () use ($user, $data) {
            $learningPath = LearningPath::create([
                'uuid' => Str::uuid(),
                'user_id' => $user->id,
                'slug' => $this->generateUniqueSlug($data['title']),
                ...$data,
            ]);

            // Initialize analytics
            $this->analyticsService->initializeLearningPathTracking($learningPath);

            // Log activity
            $this->logActivity($user, 'learning_path_created', $learningPath);

            return $learningPath;
        });
    }

    /**
     * Add step to learning path
     */
    public function addStepToPath(
        LearningPath $learningPath,
        array $stepData
    ): LearningPathStep {
        return DB::transaction(function () use ($learningPath, $stepData) {
            // Get next step number
            $stepNumber = $learningPath->steps()->max('step_number') + 1;

            $step = $learningPath->steps()->create([
                'step_number' => $stepNumber,
                ...$stepData,
            ]);

            // Update learning path totals
            $learningPath->updateStepCount();
            $this->updateEstimatedTime($learningPath);

            return $step;
        });
    }

    /**
     * Reorder learning path steps
     */
    public function reorderSteps(LearningPath $learningPath, array $stepOrder): void
    {
        DB::transaction(function () use ($learningPath, $stepOrder) {
            foreach ($stepOrder as $position => $stepId) {
                $learningPath->steps()
                    ->where('id', $stepId)
                    ->update(['step_number' => $position + 1]);
            }
        });
    }

    /**
     * Enroll user in learning path
     */
    public function enrollUser(LearningPath $learningPath, User $user): UserProgress
    {
        // Check prerequisites
        if (!$learningPath->arePrerequisitesMetFor($user)) {
            throw new \Exception('Prerequisites not met for this learning path');
        }

        $progress = $learningPath->enrollUser($user);

        // Log activity
        $this->logActivity($user, 'learning_path_enrolled', $learningPath);

        return $progress;
    }

    /**
     * Record step completion
     */
    public function completeStep(
        LearningPathStep $step,
        User $user,
        array $completionData = []
    ): void {
        DB::transaction(function () use ($step, $user, $completionData) {
            $learningPath = $step->learningPath;
            $progress = $learningPath->getUserProgress($user);

            if (!$progress) {
                throw new \Exception('User not enrolled in this learning path');
            }

            // Mark step as completed
            $progress->markStepCompleted($step->id, $completionData);

            // Update current step to next incomplete step
            $nextStep = $this->getNextIncompleteStep($learningPath, $user);
            if ($nextStep) {
                $progress->update(['current_step_id' => $nextStep->id]);
            }

            // Check if learning path is completed
            if ($progress->steps_completed >= $learningPath->total_steps) {
                $this->completePathForUser($learningPath, $user);
            }

            // Record step completion analytics
            $step->recordCompletion($user);

            // Award points
            $points = $step->points_reward ?: 10; // Default points
            $progress->awardPoints($points, 'step_completed');
        });
    }

    /**
     * Record assessment attempt
     */
    public function recordAssessmentAttempt(
        LearningPathStep $step,
        User $user,
        float $score,
        array $answers = []
    ): array {
        return DB::transaction(function () use ($step, $user, $score, $answers) {
            $learningPath = $step->learningPath;
            $progress = $learningPath->getUserProgress($user);

            if (!$progress) {
                throw new \Exception('User not enrolled in this learning path');
            }

            // Record the attempt
            $progress->recordAssessmentAttempt($step->id, $score, $answers);

            // Check if passed
            $passingScore = $step->passing_score ?? 70;
            $passed = $score >= $passingScore;

            if ($passed && $step->is_required) {
                $this->completeStep($step, $user, [
                    'assessment_score' => $score,
                    'passed' => true,
                ]);
            }

            return [
                'score' => $score,
                'passed' => $passed,
                'passing_score' => $passingScore,
                'attempts_used' => $progress->attempts_count,
                'can_retake' => $this->canRetakeAssessment($step, $progress),
            ];
        });
    }

    /**
     * Complete learning path for user
     */
    public function completePathForUser(LearningPath $learningPath, User $user): void
    {
        $progress = $learningPath->getUserProgress($user);

        if (!$progress) {
            throw new \Exception('User not enrolled in this learning path');
        }

        // Calculate final score if applicable
        $finalScore = $this->calculateFinalScore($learningPath, $progress);

        // Mark as completed
        $progress->markCompleted($finalScore);

        // Update learning path stats
        $learningPath->increment('completions_count');
        $learningPath->updateCompletionStats();

        // Issue certificate if eligible
        if ($learningPath->has_certificate && $progress->passed) {
            $certificateId = $learningPath->issueCertificate($user);
            
            if ($certificateId) {
                // Log certificate issuance
                $this->logActivity($user, 'certificate_earned', $learningPath, [
                    'certificate_id' => $certificateId,
                ]);
            }
        }

        // Award completion points and badges
        $progress->awardPoints(100, 'path_completed');
        $progress->awardBadge('path_completed', [
            'name' => 'Path Complete',
            'path_title' => $learningPath->title,
        ]);

        // Log activity
        $this->logActivity($user, 'learning_path_completed', $learningPath);
    }

    /**
     * Get user's learning path progress
     */
    public function getUserProgress(LearningPath $learningPath, User $user): ?UserProgress
    {
        return $learningPath->getUserProgress($user);
    }

    /**
     * Get user's learning dashboard data
     */
    public function getUserLearningDashboard(User $user): array
    {
        $cacheKey = "user:learning_dashboard:{$user->id}";
        
        return Cache::remember($cacheKey, 1800, function () use ($user) {
            // Get active learning paths
            $activePaths = UserProgress::where('user_id', $user->id)
                ->whereHasMorph('progressable', [LearningPath::class])
                ->with(['progressable'])
                ->inProgress()
                ->get();

            // Get completed learning paths
            $completedPaths = UserProgress::where('user_id', $user->id)
                ->whereHasMorph('progressable', [LearningPath::class])
                ->with(['progressable'])
                ->completed()
                ->get();

            // Get achievements
            $achievements = $this->getUserAchievements($user);

            // Get recommended paths
            $recommendedPaths = $this->getRecommendedPaths($user, 6);

            // Get overall stats
            $stats = $this->getUserLearningStats($user);

            return [
                'active_paths' => $activePaths,
                'completed_paths' => $completedPaths,
                'achievements' => $achievements,
                'recommended_paths' => $recommendedPaths,
                'stats' => $stats,
            ];
        });
    }

    /**
     * Get recommended learning paths for user
     */
    public function getRecommendedPaths(User $user, int $limit = 10): Collection
    {
        // Get user's completed and in-progress paths
        $userPathIds = UserProgress::where('user_id', $user->id)
            ->whereHasMorph('progressable', [LearningPath::class])
            ->pluck('progressable_id');

        // Get user's interests from profile or past activity
        $userGenres = $this->getUserGenrePreferences($user);
        $userDifficultyLevel = $this->getUserPreferredDifficulty($user);

        $query = LearningPath::query()
            ->published()
            ->whereNotIn('id', $userPathIds)
            ->with(['user:id,name,avatar_path']);

        // Prefer paths matching user preferences
        if (!empty($userGenres)) {
            $query->whereIn('path_type', $userGenres);
        }

        if ($userDifficultyLevel) {
            $query->where('difficulty_level', $userDifficultyLevel);
        }

        return $query->orderBy('average_rating', 'desc')
            ->orderBy('enrollments_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get featured learning paths
     */
    public function getFeaturedPaths(int $limit = 8): Collection
    {
        return Cache::remember("learning_paths:featured:{$limit}", 3600, function () use ($limit) {
            return LearningPath::query()
                ->published()
                ->featured()
                ->with(['user:id,name,avatar_path'])
                ->orderBy('average_rating', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get learning paths by type
     */
    public function getPathsByType(string $type, int $limit = 12): Collection
    {
        return Cache::remember("learning_paths:type:{$type}:{$limit}", 3600, function () use ($type, $limit) {
            return LearningPath::query()
                ->published()
                ->byType($type)
                ->with(['user:id,name,avatar_path'])
                ->orderBy('enrollments_count', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Search learning paths
     */
    public function searchPaths(array $criteria): Collection
    {
        $query = LearningPath::published();

        if (isset($criteria['query']) && !empty($criteria['query'])) {
            $query->whereFullText(['title', 'description'], $criteria['query']);
        }

        if (isset($criteria['path_type'])) {
            $query->where('path_type', $criteria['path_type']);
        }

        if (isset($criteria['difficulty_level'])) {
            $query->where('difficulty_level', $criteria['difficulty_level']);
        }

        if (isset($criteria['has_certificate'])) {
            $query->where('has_certificate', $criteria['has_certificate']);
        }

        if (isset($criteria['is_free'])) {
            $query->where('is_free', $criteria['is_free']);
        }

        // Sorting
        $sortBy = $criteria['sort_by'] ?? 'relevance';
        match ($sortBy) {
            'newest' => $query->orderBy('published_at', 'desc'),
            'popular' => $query->orderBy('enrollments_count', 'desc'),
            'rating' => $query->orderBy('average_rating', 'desc'),
            'completion_rate' => $query->orderBy('completion_rate', 'desc'),
            default => $query->orderBy('average_rating', 'desc'),
        };

        return $query->with(['user:id,name,avatar_path'])
            ->limit($criteria['limit'] ?? 20)
            ->get();
    }

    /**
     * Get next incomplete step for user
     */
    protected function getNextIncompleteStep(LearningPath $learningPath, User $user): ?LearningPathStep
    {
        $progress = $learningPath->getUserProgress($user);
        
        if (!$progress || !$progress->step_progress) {
            return $learningPath->steps()->first();
        }

        $completedStepIds = array_keys($progress->step_progress);
        
        return $learningPath->steps()
            ->whereNotIn('id', $completedStepIds)
            ->orderBy('step_number')
            ->first();
    }

    /**
     * Calculate final score for learning path
     */
    protected function calculateFinalScore(LearningPath $learningPath, UserProgress $progress): ?float
    {
        if (!$progress->assessment_results) {
            return null;
        }

        $scores = [];
        foreach ($progress->assessment_results as $stepId => $result) {
            if (isset($result['best_score'])) {
                $scores[] = $result['best_score'];
            }
        }

        return !empty($scores) ? array_sum($scores) / count($scores) : null;
    }

    /**
     * Check if user can retake assessment
     */
    protected function canRetakeAssessment(LearningPathStep $step, UserProgress $progress): bool
    {
        $learningPath = $step->learningPath;
        
        if (!$learningPath->allow_retakes) {
            return false;
        }

        if ($learningPath->max_retakes) {
            return $progress->attempts_count < $learningPath->max_retakes;
        }

        return true;
    }

    /**
     * Get user's genre preferences from past activity
     */
    protected function getUserGenrePreferences(User $user): array
    {
        // Analyze user's past collections and paths
        $genres = [];
        
        // This could be enhanced to analyze user's actual activity
        return $genres;
    }

    /**
     * Get user's preferred difficulty level
     */
    protected function getUserPreferredDifficulty(User $user): ?string
    {
        // Analyze user's completed paths to determine skill level
        $completedPaths = UserProgress::where('user_id', $user->id)
            ->whereHasMorph('progressable', [LearningPath::class])
            ->completed()
            ->with('progressable')
            ->get();

        if ($completedPaths->isEmpty()) {
            return 'beginner';
        }

        $difficulties = $completedPaths->pluck('progressable.difficulty_level')->countBy();
        
        // Return the most common difficulty level
        return $difficulties->keys()->first();
    }

    /**
     * Get user's achievements and badges
     */
    protected function getUserAchievements(User $user): array
    {
        $allProgress = UserProgress::where('user_id', $user->id)->get();
        
        $totalBadges = $allProgress->flatMap(function ($progress) {
            return $progress->badges_earned ?? [];
        });

        $certificates = $allProgress->where('certificate_earned', true);

        return [
            'total_badges' => $totalBadges->count(),
            'recent_badges' => $totalBadges->sortByDesc('earned_at')->take(5),
            'certificates_earned' => $certificates->count(),
            'recent_certificates' => $certificates->sortByDesc('certificate_issued_at')->take(3),
            'total_points' => $allProgress->sum('points_earned'),
            'longest_streak' => $allProgress->max('streak_days'),
        ];
    }

    /**
     * Get user's learning statistics
     */
    protected function getUserLearningStats(User $user): array
    {
        $allProgress = UserProgress::where('user_id', $user->id)
            ->whereHasMorph('progressable', [LearningPath::class])
            ->get();

        return [
            'total_enrolled' => $allProgress->count(),
            'total_completed' => $allProgress->where('status', 'completed')->count(),
            'total_in_progress' => $allProgress->where('status', 'in_progress')->count(),
            'total_time_spent' => $allProgress->sum('time_spent'),
            'average_completion_rate' => $allProgress->avg('completion_percentage'),
            'certificates_earned' => $allProgress->where('certificate_earned', true)->count(),
        ];
    }

    /**
     * Update estimated time for learning path
     */
    protected function updateEstimatedTime(LearningPath $learningPath): void
    {
        $totalTime = $learningPath->steps()->sum('estimated_duration');
        $learningPath->update(['estimated_total_time' => $totalTime]);
    }

    /**
     * Generate unique slug
     */
    protected function generateUniqueSlug(string $title, int $excludeId = null): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists
     */
    protected function slugExists(string $slug, int $excludeId = null): bool
    {
        $query = LearningPath::where('slug', $slug);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Log user activity
     */
    protected function logActivity(User $user, string $action, LearningPath $learningPath, array $metadata = []): void
    {
        $user->activities()->create([
            'action' => $action,
            'subject_type' => LearningPath::class,
            'subject_id' => $learningPath->id,
            'metadata' => array_merge([
                'path_title' => $learningPath->title,
                'path_type' => $learningPath->path_type,
            ], $metadata),
        ]);
    }
}