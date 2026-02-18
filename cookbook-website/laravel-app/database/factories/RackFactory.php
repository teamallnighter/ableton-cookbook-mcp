<?php

namespace Database\Factories;

use App\Models\Rack;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rack>
 */
class RackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'slug' => fake()->slug(),
            'file_path' => 'test-racks/test-rack.adg',
            'file_size' => fake()->numberBetween(1000, 100000),
            'file_hash' => fake()->sha256(),
            'original_filename' => 'test-rack.adg',
            'rack_type' => fake()->randomElement(['AudioEffectGroupDevice', 'InstrumentGroupDevice', 'MidiEffectGroupDevice']),
            'category' => fake()->randomElement(['1', '2', '3', '4', '5', '6', '7', '8']),
            'device_count' => fake()->numberBetween(1, 10),
            'chain_count' => fake()->numberBetween(1, 4),
            'ableton_version' => fake()->randomElement(['11.3.4', '11.2.10', '11.1.6']),
            'macro_controls' => [
                ['name' => 'Macro 1', 'value' => 64, 'mapped' => true],
                ['name' => 'Macro 2', 'value' => 32, 'mapped' => false],
            ],
            'devices' => [
                ['name' => 'Compressor', 'type' => 'Audio Effect', 'preset' => 'Default'],
                ['name' => 'EQ Eight', 'type' => 'Audio Effect', 'preset' => 'Default'],
            ],
            'chains' => [
                ['name' => 'Chain 1', 'devices_count' => 2, 'active' => true],
            ],
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'downloads_count' => fake()->numberBetween(0, 1000),
            'average_rating' => fake()->randomFloat(2, 1, 5),
            'ratings_count' => fake()->numberBetween(0, 100),
            'views_count' => fake()->numberBetween(0, 5000),
            'comments_count' => fake()->numberBetween(0, 50),
            'likes_count' => fake()->numberBetween(0, 200),
            'is_public' => true,
            'is_featured' => fake()->boolean(20), // 20% chance of being featured
            'published_at' => now(),
        ];
    }

    /**
     * Indicate that the rack is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }

    /**
     * Indicate that the rack is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the rack is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }
}