<?php

namespace Database\Factories;

use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestSuite>
 */
class TestSuiteFactory extends Factory
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
            'parent_id' => null,
            'name' => fake()->unique()->words(3, true),
            'description' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * Nest the suite inside another one, inheriting its project.
     */
    public function childOf(TestSuite $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'test_project_id' => $parent->test_project_id,
            'parent_id' => $parent->getKey(),
        ]);
    }
}
