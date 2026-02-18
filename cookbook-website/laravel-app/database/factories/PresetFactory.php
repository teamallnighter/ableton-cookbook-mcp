<?php

namespace Database\Factories;

use App\Models\Preset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PresetFactory extends Factory
{
    protected $model = Preset::class;

    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'user_id' => User::factory(),
            'title' => $this->faker->words(3, true),
            'slug' => $this->faker->slug(),
            'description' => $this->faker->paragraph(),
            'file_path' => 'presets/' . $this->faker->uuid() . '.adv',
            'file_size' => $this->faker->numberBetween(1024, 1024 * 1024), // 1KB to 1MB
            'file_hash' => $this->faker->sha256(),
            'original_filename' => $this->faker->word() . '.adv',
            'device_type' => $this->faker->randomElement(['Operator', 'Wavetable', 'Simpler', 'Impulse']),
            'device_name' => $this->faker->randomElement(['Operator', 'Wavetable', 'Simpler', 'Impulse']),
            'parameters' => [
                'osc1_wave' => $this->faker->randomElement(['sine', 'square', 'saw', 'triangle']),
                'filter_cutoff' => $this->faker->numberBetween(200, 20000),
                'filter_resonance' => $this->faker->randomFloat(2, 0, 1),
                'envelope_attack' => $this->faker->randomFloat(2, 0, 2),
                'envelope_decay' => $this->faker->randomFloat(2, 0, 2),
                'envelope_sustain' => $this->faker->randomFloat(2, 0, 1),
                'envelope_release' => $this->faker->randomFloat(2, 0, 5),
            ],
            'macros' => [
                'macro1' => [
                    'name' => 'Cutoff',
                    'mappings' => ['filter_cutoff'],
                    'range' => [200, 20000],
                ],
                'macro2' => [
                    'name' => 'Resonance',
                    'mappings' => ['filter_resonance'],
                    'range' => [0, 1],
                ],
            ],
            'metadata' => [
                'created_with' => 'Live 12.0',
                'plugin_version' => '1.2.3',
                'sample_rate' => 44100,
            ],
            'category' => $this->faker->randomElement(['Lead', 'Bass', 'Pad', 'Pluck', 'Drum', 'FX']),
            'sonic_characteristics' => [
                $this->faker->randomElement(['warm', 'bright', 'dark', 'aggressive', 'smooth']),
                $this->faker->randomElement(['punchy', 'soft', 'cutting', 'mellow']),
            ],
            'key_scale' => $this->faker->optional()->randomElement(['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B']),
            'cpu_usage' => $this->faker->randomElement(['low', 'medium', 'high']),
            'ableton_version' => $this->faker->randomElement(['11.0', '11.1', '11.2', '12.0']),
            'plugin_dependencies' => $this->faker->optional()->randomElements([
                'Wavetable',
                'Operator',
                'Bass',
                'Drum Rack'
            ], $this->faker->numberBetween(0, 2)),
            'how_to_article' => $this->faker->optional()->paragraphs(3, true),
            'how_to_updated_at' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'downloads_count' => $this->faker->numberBetween(0, 500),
            'average_rating' => $this->faker->randomFloat(2, 1, 5),
            'ratings_count' => $this->faker->numberBetween(0, 100),
            'favorites_count' => $this->faker->numberBetween(0, 50),
            'is_public' => $this->faker->boolean(80),
            'is_featured' => $this->faker->boolean(10),
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'is_public' => true,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'is_public' => false,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
            'status' => 'published',
            'is_public' => true,
        ]);
    }

    public function bass(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Bass',
            'device_type' => $this->faker->randomElement(['Operator', 'Wavetable', 'Bass']),
            'sonic_characteristics' => ['deep', 'punchy', 'sub'],
        ]);
    }

    public function lead(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Lead',
            'device_type' => $this->faker->randomElement(['Operator', 'Wavetable']),
            'sonic_characteristics' => ['bright', 'cutting', 'aggressive'],
        ]);
    }

    public function pad(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Pad',
            'sonic_characteristics' => ['warm', 'soft', 'atmospheric'],
            'cpu_usage' => $this->faker->randomElement(['medium', 'high']),
        ]);
    }

    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'downloads_count' => $this->faker->numberBetween(100, 1000),
            'favorites_count' => $this->faker->numberBetween(20, 200),
            'average_rating' => $this->faker->randomFloat(2, 4.0, 5.0),
            'ratings_count' => $this->faker->numberBetween(20, 200),
        ]);
    }

    public function withHowTo(): static
    {
        return $this->state(fn (array $attributes) => [
            'how_to_article' => $this->faker->paragraphs(5, true),
            'how_to_updated_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    public function highCpu(): static
    {
        return $this->state(fn (array $attributes) => [
            'cpu_usage' => 'high',
            'device_type' => $this->faker->randomElement(['Wavetable', 'Operator']),
        ]);
    }

    public function vintage(): static
    {
        return $this->state(fn (array $attributes) => [
            'ableton_version' => '10.1',
            'sonic_characteristics' => ['warm', 'vintage', 'analog'],
        ]);
    }
}