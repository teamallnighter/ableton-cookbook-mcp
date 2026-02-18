<?php

namespace Database\Factories;

use App\Models\UserProgress;
use App\Models\User;
use App\Models\EnhancedCollection;
use App\Models\LearningPath;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserProgressFactory extends Factory
{
    protected $model = UserProgress::class;

    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-30 days', 'now');
        $totalItems = $this->faker->numberBetween(5, 20);
        $completedItems = $this->faker->numberBetween(0, $totalItems);
        $completionPercentage = $totalItems > 0 ? ($completedItems / $totalItems) * 100 : 0;

        return [
            'user_id' => User::factory(),
            'collection_id' => EnhancedCollection::factory(),
            'learning_path_id' => LearningPath::factory(),
            'enrolled_at' => $startedAt,
            'enrollment_source' => $this->faker->randomElement(['direct', 'recommendation', 'featured', 'search', 'social']),
            'is_active' => $this->faker->boolean(80),
            'completion_percentage' => round($completionPercentage, 2),
            'completed_items' => $completedItems,
            'total_items' => $totalItems,
            'required_items_completed' => $this->faker->numberBetween(0, min($completedItems, $this->faker->numberBetween(1, 10))),
            'optional_items_completed' => $this->faker->numberBetween(0, $completedItems),
            'total_time_minutes' => $this->faker->numberBetween(30, 600),
            'started_at' => $startedAt,
            'last_accessed_at' => $this->faker->dateTimeBetween($startedAt, 'now'),
            'completed_at' => $completionPercentage >= 100 ? $this->faker->dateTimeBetween($startedAt, 'now') : null,
            'session_count' => $this->faker->numberBetween(1, 50),
            'average_session_minutes' => $this->faker->numberBetween(10, 120),
            'current_step' => $this->faker->numberBetween(0, 10),
            'bookmark_data' => [
                'current_section' => $this->faker->word(),
                'current_item_id' => $this->faker->numberBetween(1, 100),
                'progress_notes' => $this->faker->sentence(),
            ],
            'item_progress' => [
                '1' => ['completed' => true, 'time_spent' => 45, 'score' => 85],
                '2' => ['completed' => true, 'time_spent' => 30, 'score' => 92],
                '3' => ['completed' => false, 'time_spent' => 15],
            ],
            'assessment_scores' => [
                'quiz_1' => 85,
                'quiz_2' => 92,
                'final_exam' => 78,
            ],
            'overall_score' => $this->faker->randomFloat(2, 60, 100),
            'retakes_used' => $this->faker->numberBetween(0, 3),
            'likes_given' => $this->faker->numberBetween(0, 10),
            'comments_made' => $this->faker->numberBetween(0, 5),
            'items_bookmarked' => $this->faker->numberBetween(0, 8),
            'favorite_items' => [
                $this->faker->numberBetween(1, 20),
                $this->faker->numberBetween(21, 40),
            ],
            'learning_style_data' => [
                'preferred_content_type' => $this->faker->randomElement(['video', 'text', 'audio', 'interactive']),
                'learning_pace' => $this->faker->randomElement(['slow', 'normal', 'fast']),
                'difficulty_preference' => $this->faker->randomElement(['step_by_step', 'challenge_me']),
            ],
            'difficulty_feedback' => [
                'item_1' => 'easy',
                'item_2' => 'just_right',
                'item_3' => 'difficult',
            ],
            'current_streak_days' => $this->faker->numberBetween(0, 30),
            'longest_streak_days' => $this->faker->numberBetween(0, 60),
            'streak_started_at' => $this->faker->dateTimeBetween('-60 days', 'now'),
            'total_points_earned' => $this->faker->numberBetween(0, 1000),
            'badges_earned' => [
                [
                    'badge_id' => 'first_completion',
                    'badge_name' => 'First Steps',
                    'earned_at' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d H:i:s'),
                ],
                [
                    'badge_id' => 'streak_7',
                    'badge_name' => 'Week Warrior',
                    'earned_at' => $this->faker->dateTimeBetween('-20 days', 'now')->format('Y-m-d H:i:s'),
                ],
            ],
            'progress_public' => $this->faker->boolean(30),
            'allow_collaboration' => $this->faker->boolean(70),
            'personal_notes' => $this->faker->paragraph(),
            'eligible_for_certificate' => $completionPercentage >= 100 ? $this->faker->boolean(80) : false,
            'certificate_id' => null,
            'certificate_issued_at' => null,
            'certificate_expires_at' => null,
            'certificate_revoked' => false,
            'user_rating' => $this->faker->optional()->randomFloat(1, 1, 5),
            'user_review' => $this->faker->optional()->paragraph(),
            'would_recommend' => $this->faker->optional()->boolean(),
            'needs_attention' => $this->faker->boolean(5),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'completion_percentage' => $this->faker->randomFloat(2, 10, 95),
            'completed_at' => null,
            'is_active' => true,
            'last_accessed_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completion_percentage' => 100,
            'completed_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'is_active' => false,
            'eligible_for_certificate' => $this->faker->boolean(80),
            'overall_score' => $this->faker->randomFloat(2, 70, 100),
        ]);
    }

    public function withCertificate(): static
    {
        return $this->state(function (array $attributes) {
            $issuedAt = $this->faker->dateTimeBetween('-30 days', 'now');
            return [
                'completion_percentage' => 100,
                'completed_at' => $issuedAt,
                'eligible_for_certificate' => true,
                'certificate_id' => $this->faker->uuid(),
                'certificate_issued_at' => $issuedAt,
                'certificate_expires_at' => $this->faker->dateTimeBetween('+1 year', '+3 years'),
                'overall_score' => $this->faker->randomFloat(2, 80, 100),
            ];
        });
    }

    public function notStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'completion_percentage' => 0,
            'completed_items' => 0,
            'started_at' => null,
            'last_accessed_at' => null,
            'session_count' => 0,
            'total_time_minutes' => 0,
        ]);
    }

    public function highPerformer(): static
    {
        return $this->state(fn (array $attributes) => [
            'overall_score' => $this->faker->randomFloat(2, 90, 100),
            'completion_percentage' => 100,
            'total_points_earned' => $this->faker->numberBetween(800, 1500),
            'current_streak_days' => $this->faker->numberBetween(15, 60),
            'user_rating' => $this->faker->randomFloat(1, 4.5, 5.0),
            'would_recommend' => true,
        ]);
    }

    public function struggling(): static
    {
        return $this->state(fn (array $attributes) => [
            'completion_percentage' => $this->faker->randomFloat(2, 5, 30),
            'overall_score' => $this->faker->randomFloat(2, 40, 65),
            'retakes_used' => $this->faker->numberBetween(2, 5),
            'needs_attention' => true,
            'last_accessed_at' => $this->faker->dateTimeBetween('-14 days', '-7 days'),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'last_accessed_at' => $this->faker->dateTimeBetween('-2 days', 'now'),
            'current_streak_days' => $this->faker->numberBetween(1, 15),
        ]);
    }

    public function enrolled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enrolled_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'is_active' => true,
            'completion_percentage' => 0,
            'completed_items' => 0,
        ]);
    }
}