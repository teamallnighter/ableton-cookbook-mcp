<?php

namespace Database\Factories;

use App\Models\EnhancedCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnhancedCollectionFactory extends Factory
{
    protected $model = EnhancedCollection::class;

    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'user_id' => User::factory(),
            'title' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'slug' => $this->faker->slug(),
            'collection_type' => $this->faker->randomElement(['manual', 'smart', 'learning_path']),
            'visibility' => $this->faker->randomElement(['public', 'unlisted', 'private']),
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
            'category' => $this->faker->randomElement(['Electronic', 'House', 'Techno', 'Ambient', 'Hip Hop']),
            'difficulty_level' => $this->faker->numberBetween(1, 5),
            'items_count' => 0,
            'views_count' => $this->faker->numberBetween(0, 1000),
            'downloads_count' => $this->faker->numberBetween(0, 100),
            'saves_count' => $this->faker->numberBetween(0, 50),
            'likes_count' => $this->faker->numberBetween(0, 200),
            'average_rating' => $this->faker->randomFloat(2, 1, 5),
            'reviews_count' => $this->faker->numberBetween(0, 25),
            'is_featured' => $this->faker->boolean(10), // 10% chance
            'tags' => $this->faker->words(3),
            'version' => '1.0',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now()->subDays($this->faker->numberBetween(1, 30)),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
            'featured_until' => now()->addDays($this->faker->numberBetween(1, 30)),
        ]);
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'public',
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'private',
        ]);
    }

    public function withItems(int $count = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'items_count' => $count,
        ]);
    }

    public function learningPath(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection_type' => 'learning_path',
            'has_learning_path' => true,
        ]);
    }

    public function genreCookbook(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection_type' => 'genre_cookbook',
            'category' => $this->faker->randomElement(['House', 'Techno', 'Trance', 'Drum & Bass']),
        ]);
    }

    public function quickStartPack(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection_type' => 'quick_start_pack',
            'difficulty_level' => 1,
        ]);
    }

    public function highlyRated(): static
    {
        return $this->state(fn (array $attributes) => [
            'average_rating' => $this->faker->randomFloat(2, 4.0, 5.0),
            'reviews_count' => $this->faker->numberBetween(10, 100),
        ]);
    }

    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'views_count' => $this->faker->numberBetween(500, 5000),
            'downloads_count' => $this->faker->numberBetween(50, 500),
            'saves_count' => $this->faker->numberBetween(25, 250),
        ]);
    }
}