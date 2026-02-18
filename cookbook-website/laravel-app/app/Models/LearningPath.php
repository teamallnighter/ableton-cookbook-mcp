<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Services\MarkdownService;

/**
 * @OA\Schema(
 *     schema="LearningPath",
 *     type="object",
 *     title="Learning Path",
 *     description="Structured educational path with progress tracking",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="uuid", type="string", format="uuid"),
 *     @OA\Property(property="title", type="string", example="Mastering Progressive House"),
 *     @OA\Property(property="description", type="string", example="Complete learning path for progressive house production"),
 *     @OA\Property(property="path_type", type="string", enum={"skill_building", "genre_mastery", "production_workflow", "sound_design", "mixing_mastering", "performance_setup", "custom"}),
 *     @OA\Property(property="difficulty_level", type="string", enum={"beginner", "intermediate", "advanced"}),
 *     @OA\Property(property="estimated_total_time", type="number", format="float", example=25.5),
 *     @OA\Property(property="total_steps", type="integer", example=12),
 *     @OA\Property(property="has_certificate", type="boolean", example=true),
 *     @OA\Property(property="enrollments_count", type="integer", example=150),
 *     @OA\Property(property="completions_count", type="integer", example=89),
 *     @OA\Property(property="completion_rate", type="number", format="float", example=59.33)
 * )
 */
class LearningPath extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'description',
        'how_to_article',
        'slug',
        'cover_image_path',
        'path_type',
        'difficulty_level',
        'estimated_total_time',
        'total_steps',
        'prerequisites',
        'required_software',
        'required_hardware',
        'learning_objectives',
        'skills_taught',
        'has_certificate',
        'certificate_template',
        'certificate_requirements',
        'passing_score',
        'is_free',
        'path_price',
        'is_public',
        'is_featured',
        'status',
        'published_at',
        'track_time_spent',
        'require_sequential_completion',
        'allow_retakes',
        'max_retakes',
        'enrollments_count',
        'completions_count',
        'completion_rate',
        'average_rating',
        'ratings_count',
        'average_completion_time',
    ];

    protected $casts = [
        'prerequisites' => 'array',
        'required_software' => 'array',
        'required_hardware' => 'array',
        'learning_objectives' => 'array',
        'skills_taught' => 'array',
        'certificate_requirements' => 'array',
        'published_at' => 'datetime',
        'estimated_total_time' => 'decimal:2',
        'passing_score' => 'decimal:2',
        'path_price' => 'decimal:2',
        'completion_rate' => 'decimal:2',
        'average_rating' => 'decimal:2',
        'average_completion_time' => 'decimal:2',
        'is_free' => 'boolean',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'has_certificate' => 'boolean',
        'track_time_spent' => 'boolean',
        'require_sequential_completion' => 'boolean',
        'allow_retakes' => 'boolean',
    ];

    /**
     * Get the user that created this learning path
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the steps in this learning path
     */
    public function steps(): HasMany
    {
        return $this->hasMany(LearningPathStep::class)->orderBy('step_number');
    }

    /**
     * Get user progress for this learning path
     */
    public function userProgress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'progressable');
    }

    /**
     * Get analytics for this learning path
     */
    public function analytics(): MorphMany
    {
        return $this->morphMany(CollectionAnalytics::class, 'analyticsable');
    }

    /**
     * Get the ratings for this learning path
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(LearningPathRating::class);
    }

    /**
     * Get the comments for this learning path
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Get activity feed entries for this learning path
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(UserActivityFeed::class, 'subject');
    }

    // SCOPES

    /**
     * Scope for published learning paths
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'approved')
            ->where('is_public', true)
            ->whereNotNull('published_at');
    }

    /**
     * Scope for featured learning paths
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope by path type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('path_type', $type);
    }

    /**
     * Scope by difficulty level
     */
    public function scopeByDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty_level', $difficulty);
    }

    /**
     * Scope for free learning paths
     */
    public function scopeFree($query)
    {
        return $query->where('is_free', true);
    }

    /**
     * Scope for paths with certificates
     */
    public function scopeWithCertificate($query)
    {
        return $query->where('has_certificate', true);
    }

    // ACCESSORS

    /**
     * Get display name for path type
     */
    public function getPathTypeDisplayAttribute(): string
    {
        $typeMap = [
            'skill_building' => 'Skill Building',
            'genre_mastery' => 'Genre Mastery',
            'production_workflow' => 'Production Workflow',
            'sound_design' => 'Sound Design',
            'mixing_mastering' => 'Mixing & Mastering',
            'performance_setup' => 'Performance Setup',
            'custom' => 'Custom Learning Path',
        ];
        
        return $typeMap[$this->path_type] ?? $this->path_type ?? 'Learning Path';
    }

    /**
     * Get HTML version of how-to article
     */
    public function getHtmlHowToAttribute(): ?string
    {
        if (empty($this->how_to_article)) {
            return null;
        }

        return app(MarkdownService::class)->parseToHtml($this->how_to_article);
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->is_free) {
            return 'Free';
        }

        return '$' . number_format($this->path_price, 2);
    }

    /**
     * Get formatted total time
     */
    public function getFormattedTotalTimeAttribute(): string
    {
        if (!$this->estimated_total_time) {
            return 'Unknown';
        }

        $hours = floor($this->estimated_total_time);
        $minutes = ($this->estimated_total_time - $hours) * 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    /**
     * Get formatted completion rate
     */
    public function getFormattedCompletionRateAttribute(): string
    {
        return number_format($this->completion_rate, 1) . '%';
    }

    /**
     * Get difficulty level color
     */
    public function getDifficultyColorAttribute(): string
    {
        $colorMap = [
            'beginner' => 'green',
            'intermediate' => 'yellow',
            'advanced' => 'red',
        ];

        return $colorMap[$this->difficulty_level] ?? 'gray';
    }

    // BUSINESS METHODS

    /**
     * Check if user is enrolled in this path
     */
    public function hasEnrolledUser(User $user): bool
    {
        return $this->userProgress()->where('user_id', $user->id)->exists();
    }

    /**
     * Enroll user in this learning path
     */
    public function enrollUser(User $user): UserProgress
    {
        $progress = $this->userProgress()->firstOrCreate([
            'user_id' => $user->id,
        ], [
            'status' => 'not_started',
            'total_steps' => $this->total_steps,
            'started_at' => now(),
        ]);

        if ($progress->wasRecentlyCreated) {
            $this->increment('enrollments_count');
        }

        return $progress;
    }

    /**
     * Get user's progress on this path
     */
    public function getUserProgress(User $user): ?UserProgress
    {
        return $this->userProgress()->where('user_id', $user->id)->first();
    }

    /**
     * Check if user has completed this path
     */
    public function isCompletedBy(User $user): bool
    {
        $progress = $this->getUserProgress($user);
        return $progress && $progress->status === 'completed';
    }

    /**
     * Check if user has earned certificate for this path
     */
    public function hasUserEarnedCertificate(User $user): bool
    {
        if (!$this->has_certificate) {
            return false;
        }

        $progress = $this->getUserProgress($user);
        return $progress && $progress->certificate_earned;
    }

    /**
     * Get next step for user
     */
    public function getNextStepFor(User $user): ?LearningPathStep
    {
        $progress = $this->getUserProgress($user);
        
        if (!$progress) {
            return $this->steps()->first();
        }

        if ($progress->current_step_id) {
            $currentStep = $this->steps()->find($progress->current_step_id);
            if ($currentStep) {
                return $this->steps()
                    ->where('step_number', '>', $currentStep->step_number)
                    ->first();
            }
        }

        // Return first incomplete step
        $completedSteps = $progress->step_progress ?? [];
        
        return $this->steps()
            ->whereNotIn('id', array_keys($completedSteps))
            ->first();
    }

    /**
     * Check if prerequisites are met for user
     */
    public function arePrerequisitesMetFor(User $user): bool
    {
        if (empty($this->prerequisites)) {
            return true;
        }

        // Implementation would check prerequisites
        // This is a simplified version
        return true;
    }

    /**
     * Update completion statistics
     */
    public function updateCompletionStats(): void
    {
        $totalEnrollments = $this->enrollments_count;
        $totalCompletions = $this->completions_count;

        $completionRate = $totalEnrollments > 0 
            ? ($totalCompletions / $totalEnrollments) * 100 
            : 0;

        // Calculate average completion time
        $avgTime = $this->userProgress()
            ->where('status', 'completed')
            ->whereNotNull('time_spent')
            ->avg('time_spent');

        $this->update([
            'completion_rate' => $completionRate,
            'average_completion_time' => $avgTime,
        ]);
    }

    /**
     * Update step count
     */
    public function updateStepCount(): void
    {
        $this->update([
            'total_steps' => $this->steps()->count(),
        ]);
    }

    /**
     * Update average rating
     */
    public function updateAverageRating(): void
    {
        $stats = $this->ratings()
            ->selectRaw('AVG(rating) as average, COUNT(*) as count')
            ->first();

        $this->update([
            'average_rating' => $stats->average ?? 0,
            'ratings_count' => $stats->count ?? 0,
        ]);
    }

    /**
     * Issue certificate to user
     */
    public function issueCertificate(User $user): ?string
    {
        if (!$this->has_certificate) {
            return null;
        }

        $progress = $this->getUserProgress($user);
        if (!$progress || !$progress->passed) {
            return null;
        }

        if ($progress->certificate_earned) {
            return $progress->certificate_id;
        }

        $certificateId = 'CERT-' . strtoupper(Str::random(12));
        
        $progress->update([
            'certificate_earned' => true,
            'certificate_issued_at' => now(),
            'certificate_id' => $certificateId,
            'certificate_data' => [
                'path_title' => $this->title,
                'user_name' => $user->name,
                'completion_date' => now()->format('Y-m-d'),
                'score' => $progress->best_score,
                'time_spent' => $progress->time_spent,
            ],
        ]);

        return $certificateId;
    }

    /**
     * Get learning path progress summary
     */
    public function getProgressSummary(): array
    {
        return [
            'total_enrollments' => $this->enrollments_count,
            'total_completions' => $this->completions_count,
            'completion_rate' => $this->completion_rate,
            'average_rating' => $this->average_rating,
            'ratings_count' => $this->ratings_count,
            'average_completion_time' => $this->average_completion_time,
            'certificates_issued' => $this->userProgress()
                ->where('certificate_earned', true)->count(),
        ];
    }
}