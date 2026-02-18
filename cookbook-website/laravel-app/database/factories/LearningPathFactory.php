<?php

namespace Database\Factories;

use App\Models\LearningPath;
use App\Models\EnhancedCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

class LearningPathFactory extends Factory
{
    protected $model = LearningPath::class;

    public function definition(): array
    {
        return [
            'collection_id' => EnhancedCollection::factory(),
            'title' => $this->faker->words(4, true),
            'description' => $this->faker->paragraph(),
            'learning_objectives' => [
                $this->faker->sentence(),
                $this->faker->sentence(),
                $this->faker->sentence(),
            ],
            'prerequisites' => null,
            'estimated_hours' => $this->faker->randomFloat(1, 1, 20),
            'difficulty_level' => $this->faker->randomElement(['beginner', 'intermediate', 'advanced', 'expert']),
            'path_structure' => [
                'modules' => [
                    [
                        'title' => 'Introduction',
                        'description' => 'Getting started',
                        'estimated_time' => 30,
                    ],
                    [
                        'title' => 'Core Concepts',
                        'description' => 'Learning the basics',
                        'estimated_time' => 60,
                    ],
                    [
                        'title' => 'Advanced Techniques',
                        'description' => 'Mastering the craft',
                        'estimated_time' => 90,
                    ],
                ]
            ],
            'total_steps' => $this->faker->numberBetween(3, 15),
            'required_steps' => $this->faker->numberBetween(2, 10),
            'allow_skip_optional' => $this->faker->boolean(70),
            'enforce_sequence' => $this->faker->boolean(30),
            'has_final_assessment' => $this->faker->boolean(50),
            'assessment_config' => [
                'type' => 'quiz',
                'question_count' => $this->faker->numberBetween(5, 20),
                'time_limit' => $this->faker->numberBetween(10, 60),
            ],
            'passing_score' => $this->faker->randomFloat(2, 60, 85),
            'allows_retakes' => $this->faker->boolean(80),
            'max_retakes' => $this->faker->numberBetween(2, 5),
            'issues_certificate' => $this->faker->boolean(40),
            'certificate_template' => null,
            'tags' => $this->faker->words(3),
            'target_audience' => $this->faker->sentence(),
            'skills_learned' => [
                $this->faker->word(),
                $this->faker->word(),
                $this->faker->word(),
            ],
            'enrollment_count' => $this->faker->numberBetween(0, 200),
            'completion_count' => $this->faker->numberBetween(0, 100),
            'average_completion_time' => $this->faker->randomFloat(2, 1, 15),
            'completion_rate' => $this->faker->randomFloat(2, 0, 100),
            'average_rating' => $this->faker->randomFloat(2, 1, 5),
            'ratings_count' => $this->faker->numberBetween(0, 50),
            'is_active' => true,
            'is_featured' => $this->faker->boolean(10),
            'status' => $this->faker->randomElement(['draft', 'published', 'archived', 'under_review']),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'is_active' => true,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'is_active' => false,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
            'status' => 'published',
            'is_active' => true,
        ]);
    }

    public function beginner(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty_level' => 'beginner',
            'estimated_hours' => $this->faker->randomFloat(1, 1, 5),
            'total_steps' => $this->faker->numberBetween(3, 8),
        ]);
    }

    public function advanced(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty_level' => 'advanced',
            'estimated_hours' => $this->faker->randomFloat(1, 10, 25),
            'total_steps' => $this->faker->numberBetween(10, 20),
            'prerequisites' => [
                'required_paths' => [$this->faker->numberBetween(1, 5)],
                'minimum_experience' => 'intermediate',
            ],
        ]);
    }

    public function withCertificate(): static
    {
        return $this->state(fn (array $attributes) => [
            'issues_certificate' => true,
            'has_final_assessment' => true,
            'passing_score' => $this->faker->randomFloat(2, 70, 85),
            'certificate_template' => 'default_certificate',
        ]);
    }

    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'enrollment_count' => $this->faker->numberBetween(100, 1000),
            'completion_count' => $this->faker->numberBetween(50, 500),
            'completion_rate' => $this->faker->randomFloat(2, 60, 90),
            'average_rating' => $this->faker->randomFloat(2, 4.0, 5.0),
            'ratings_count' => $this->faker->numberBetween(20, 200),
        ]);
    }

    public function withPrerequisites(): static
    {
        return $this->state(fn (array $attributes) => [
            'prerequisites' => [
                'required_paths' => [
                    $this->faker->numberBetween(1, 10),
                    $this->faker->numberBetween(11, 20),
                ],
                'required_certificates' => [
                    $this->faker->word(),
                ],
                'minimum_experience' => 'beginner',
            ],
        ]);
    }
}