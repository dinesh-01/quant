<?php

namespace Database\Factories;

use App\Models\TestPlan;
use App\Models\TestProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestPlan>
 */
class TestPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_project_id' => TestProject::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => null,
            'is_active' => true,
            'is_open' => true,
            'is_public' => true,
        ];
    }

    /**
     * Indicate that the plan no longer accepts execution results.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_open' => false,
        ]);
    }

    /**
     * Indicate that the plan is only reachable by users who hold a role for it.
     */
    public function restricted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_public' => false,
        ]);
    }
}
