<?php

namespace Database\Factories;

use App\Models\Build;
use App\Models\TestPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Build>
 */
class BuildFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_plan_id' => TestPlan::factory(),
            'name' => fake()->unique()->bothify('build-###??'),
            'notes' => null,
            'is_active' => true,
            'is_open' => true,
            'release_date' => null,
            'author_id' => null,
        ];
    }

    public function named(string $name): static
    {
        return $this->state(['name' => $name]);
    }

    /**
     * Indicate that the build no longer accepts execution results.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_open' => false,
        ]);
    }
}
