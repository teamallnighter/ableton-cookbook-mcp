<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @OA\Schema(
 *     schema="UserProgress",
 *     type="object",
 *     title="User Progress",
 *     description="User's progress on collections and learning paths",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=123),
 *     @OA\Property(property="progressable_type", type="string", example="App\\Models\\LearningPath"),
 *     @OA\Property(property="progressable_id", type="integer", example=42),
 *     @OA\Property(property="status", type="string", enum={"not_started", "in_progress", "completed", "paused", "failed"}),
 *     @OA\Property(property="completion_percentage", type="number", format="float", example=75.5),
 *     @OA\Property(property="items_completed", type="integer", example=8),
 *     @OA\Property(property="total_items", type="integer", example=12),
 *     @OA\Property(property="time_spent", type="number", format="float", example=15.75),
 *     @OA\Property(property="best_score", type="number", format="float", example=87.5),
 *     @OA\Property(property="certificate_earned", type="boolean", example=false),
 *     @OA\Property(property="started_at", type="string", format="date-time"),
 *     @OA\Property(property="last_accessed_at", type="string", format="date-time")
 * )
 */
class UserProgress extends Model
{
    use HasFactory;

    protected $table = 'user_progress';

    protected $fillable = [
        'user_id',
        'progressable_type',
        'progressable_id',
        'status',
        'started_at',
        'completed_at',
        'last_accessed_at',
        'time_spent',
        'completion_percentage',
        'items_completed',
        'total_items',
        'current_step_id',
        'steps_completed',
        'total_steps',
        'best_score',
        'latest_score',
        'attempts_count',
        'passed',
        'completed_items',
        'step_progress',
        'assessment_results',
        'bookmarks',
        'notes',
        'points_earned',
        'badges_earned',
        'streak_days',
        'last_activity_date',
        'certificate_earned',
        'certificate_issued_at',
        'certificate_id',
        'certificate_data',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'certificate_issued_at' => 'datetime',
        'last_activity_date' => 'date',
        'time_spent' => 'decimal:2',
        'completion_percentage' => 'decimal:2',
        'best_score' => 'decimal:2',
        'latest_score' => 'decimal:2',
        'passed' => 'boolean',
        'certificate_earned' => 'boolean',
        'completed_items' => 'array',
        'step_progress' => 'array',
        'assessment_results' => 'array',
        'bookmarks' => 'array',
        'notes' => 'array',
        'badges_earned' => 'array',
        'certificate_data' => 'array',
    ];

    /**
     * Get the user this progress belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the progressable model (Collection, LearningPath, etc.)
     */
    public function progressable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the current step for learning paths
     */
    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(LearningPathStep::class, 'current_step_id');
    }

    // SCOPES

    /**
     * Scope for specific status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for completed progress
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for in progress
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope for progress with certificates
     */
    public function scopeWithCertificate($query)
    {
        return $query->where('certificate_earned', true);
    }

    /**
     * Scope for recent activity
     */
    public function scopeRecentActivity($query, int $days = 7)
    {
        return $query->where('last_accessed_at', '>=', now()->subDays($days));
    }

    // ACCESSORS

    /**
     * Get formatted completion percentage
     */
    public function getFormattedCompletionPercentageAttribute(): string
    {
        return number_format($this->completion_percentage, 1) . '%';
    }

    /**
     * Get formatted time spent
     */
    public function getFormattedTimeSpentAttribute(): string
    {
        if (!$this->time_spent) {
            return '0h 0m';
        }

        $hours = floor($this->time_spent);
        $minutes = ($this->time_spent - $hours) * 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    /**
     * Get status display name
     */
    public function getStatusDisplayAttribute(): string
    {
        $statusMap = [
            'not_started' => 'Not Started',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'paused' => 'Paused',
            'failed' => 'Failed',
        ];

        return $statusMap[$this->status] ?? $this->status;
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        $colorMap = [
            'not_started' => 'gray',
            'in_progress' => 'blue',
            'completed' => 'green',
            'paused' => 'yellow',
            'failed' => 'red',
        ];

        return $colorMap[$this->status] ?? 'gray';
    }

    /**
     * Get progress bar percentage (0-100)
     */
    public function getProgressBarPercentageAttribute(): float
    {
        return min(100, max(0, $this->completion_percentage));
    }

    /**
     * Get next milestone percentage
     */
    public function getNextMilestonePercentageAttribute(): int
    {
        $milestones = [25, 50, 75, 100];
        
        foreach ($milestones as $milestone) {
            if ($this->completion_percentage < $milestone) {
                return $milestone;
            }
        }
        
        return 100;
    }

    // BUSINESS METHODS

    /**
     * Start progress tracking
     */
    public function start(): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => $this->started_at ?? now(),
            'last_accessed_at' => now(),
        ]);
    }

    /**
     * Mark as completed
     */
    public function markCompleted(float $finalScore = null): void
    {
        $this->update([
            'status' => 'completed',
            'completion_percentage' => 100,
            'completed_at' => now(),
            'last_accessed_at' => now(),
            'latest_score' => $finalScore,
            'best_score' => $finalScore ? max($this->best_score ?? 0, $finalScore) : $this->best_score,
        ]);

        // Update parent model completion count
        if ($this->progressable) {
            $this->progressable->increment('completions_count');
        }
    }

    /**
     * Update progress
     */
    public function updateProgress(): void
    {
        if ($this->status === 'completed') {
            return;
        }

        $completionPercentage = 0;

        if ($this->total_items > 0) {
            $completionPercentage = ($this->items_completed / $this->total_items) * 100;
        } elseif ($this->total_steps > 0) {
            $completionPercentage = ($this->steps_completed / $this->total_steps) * 100;
        }

        $status = $this->status;
        if ($completionPercentage >= 100) {
            $status = 'completed';
            $this->completed_at = now();
        } elseif ($completionPercentage > 0 && $status === 'not_started') {
            $status = 'in_progress';
        }

        $this->update([
            'completion_percentage' => $completionPercentage,
            'status' => $status,
            'last_accessed_at' => now(),
        ]);

        // Check for certificate eligibility
        $this->checkCertificateEligibility();
    }

    /**
     * Record time spent
     */
    public function addTimeSpent(float $hours): void
    {
        $this->increment('time_spent', $hours);
        $this->update(['last_accessed_at' => now()]);
        $this->updateStreak();
    }

    /**
     * Mark item as completed
     */
    public function markItemCompleted(int $itemId): void
    {
        $completedItems = $this->completed_items ?? [];
        
        if (!in_array($itemId, $completedItems)) {
            $completedItems[] = $itemId;
            
            $this->update([
                'completed_items' => $completedItems,
                'items_completed' => count($completedItems),
            ]);
            
            $this->updateProgress();
        }
    }

    /**
     * Mark step as completed (for learning paths)
     */
    public function markStepCompleted(int $stepId, array $data = []): void
    {
        $stepProgress = $this->step_progress ?? [];
        
        if (!isset($stepProgress[$stepId])) {
            $stepProgress[$stepId] = array_merge([
                'completed_at' => now()->toISOString(),
                'attempts' => 1,
            ], $data);
            
            $this->update([
                'step_progress' => $stepProgress,
                'steps_completed' => count($stepProgress),
                'current_step_id' => $stepId,
            ]);
            
            $this->updateProgress();
        }
    }

    /**
     * Record assessment attempt
     */
    public function recordAssessmentAttempt(int $stepId, float $score, array $answers = []): void
    {
        $assessmentResults = $this->assessment_results ?? [];
        
        if (!isset($assessmentResults[$stepId])) {
            $assessmentResults[$stepId] = [
                'attempts' => [],
                'best_score' => 0,
                'passed' => false,
            ];
        }
        
        $attemptData = [
            'score' => $score,
            'attempted_at' => now()->toISOString(),
            'answers' => $answers,
        ];
        
        $assessmentResults[$stepId]['attempts'][] = $attemptData;
        $assessmentResults[$stepId]['best_score'] = max($assessmentResults[$stepId]['best_score'], $score);
        
        // Check if passed (assuming 70% is passing)
        $passingScore = 70; // This could come from step configuration
        $assessmentResults[$stepId]['passed'] = $score >= $passingScore;
        
        $this->update([
            'assessment_results' => $assessmentResults,
            'latest_score' => $score,
            'best_score' => max($this->best_score ?? 0, $score),
            'attempts_count' => $this->attempts_count + 1,
        ]);
    }

    /**
     * Add bookmark
     */
    public function addBookmark(string $type, array $data): void
    {
        $bookmarks = $this->bookmarks ?? [];
        $bookmarks[] = array_merge([
            'type' => $type,
            'created_at' => now()->toISOString(),
        ], $data);
        
        $this->update(['bookmarks' => $bookmarks]);
    }

    /**
     * Add personal note
     */
    public function addNote(string $content, int $itemId = null): void
    {
        $notes = $this->notes ?? [];
        $notes[] = [
            'content' => $content,
            'item_id' => $itemId,
            'created_at' => now()->toISOString(),
        ];
        
        $this->update(['notes' => $notes]);
    }

    /**
     * Award points
     */
    public function awardPoints(int $points, string $reason = ''): void
    {
        $this->increment('points_earned', $points);
        
        // Could trigger badge checks here
        $this->checkForBadges();
    }

    /**
     * Award badge
     */
    public function awardBadge(string $badgeId, array $data = []): void
    {
        $badges = $this->badges_earned ?? [];
        
        if (!in_array($badgeId, array_column($badges, 'id'))) {
            $badges[] = array_merge([
                'id' => $badgeId,
                'earned_at' => now()->toISOString(),
            ], $data);
            
            $this->update(['badges_earned' => $badges]);
        }
    }

    /**
     * Update learning streak
     */
    protected function updateStreak(): void
    {
        $today = now()->toDateString();
        
        if ($this->last_activity_date?->toDateString() === $today) {
            return; // Already updated today
        }
        
        $yesterday = now()->subDay()->toDateString();
        
        if ($this->last_activity_date?->toDateString() === $yesterday) {
            // Continue streak
            $this->increment('streak_days');
        } else {
            // Reset streak
            $this->update(['streak_days' => 1]);
        }
        
        $this->update(['last_activity_date' => $today]);
    }

    /**
     * Check certificate eligibility
     */
    protected function checkCertificateEligibility(): void
    {
        if ($this->certificate_earned || !$this->progressable) {
            return;
        }

        // Check if progressable offers certificates
        if (!method_exists($this->progressable, 'has_certificate') || !$this->progressable->has_certificate) {
            return;
        }

        // Check completion and score requirements
        $passed = $this->completion_percentage >= 100;
        
        if (method_exists($this->progressable, 'passing_score') && $this->progressable->passing_score) {
            $passed = $passed && $this->best_score >= $this->progressable->passing_score;
        }

        if ($passed) {
            $this->update(['passed' => true]);
        }
    }

    /**
     * Check for new badges
     */
    protected function checkForBadges(): void
    {
        // Implementation would check various badge criteria
        // This is a simplified version
        
        $badges = [];
        
        // Progress milestones
        if ($this->completion_percentage >= 25 && $this->completion_percentage < 50) {
            $badges[] = ['id' => 'quarter_complete', 'name' => 'Getting Started'];
        } elseif ($this->completion_percentage >= 50 && $this->completion_percentage < 75) {
            $badges[] = ['id' => 'half_complete', 'name' => 'Halfway There'];
        } elseif ($this->completion_percentage >= 75 && $this->completion_percentage < 100) {
            $badges[] = ['id' => 'almost_done', 'name' => 'Almost Done'];
        } elseif ($this->completion_percentage >= 100) {
            $badges[] = ['id' => 'completed', 'name' => 'Completed'];
        }
        
        // Streak badges
        if ($this->streak_days >= 7) {
            $badges[] = ['id' => 'week_streak', 'name' => 'Week Warrior'];
        }
        if ($this->streak_days >= 30) {
            $badges[] = ['id' => 'month_streak', 'name' => 'Consistent Learner'];
        }
        
        // Award new badges
        foreach ($badges as $badge) {
            $this->awardBadge($badge['id'], ['name' => $badge['name']]);
        }
    }

    /**
     * Get progress summary for UI
     */
    public function getProgressSummary(): array
    {
        return [
            'status' => $this->status,
            'status_display' => $this->status_display,
            'completion_percentage' => $this->completion_percentage,
            'items_completed' => $this->items_completed,
            'total_items' => $this->total_items,
            'steps_completed' => $this->steps_completed,
            'total_steps' => $this->total_steps,
            'time_spent' => $this->time_spent,
            'formatted_time_spent' => $this->formatted_time_spent,
            'best_score' => $this->best_score,
            'certificate_earned' => $this->certificate_earned,
            'certificate_id' => $this->certificate_id,
            'points_earned' => $this->points_earned,
            'badges_count' => count($this->badges_earned ?? []),
            'streak_days' => $this->streak_days,
            'last_accessed_at' => $this->last_accessed_at,
        ];
    }
}