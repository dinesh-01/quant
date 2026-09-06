<?php

namespace Database\Factories;

use App\Models\RequirementSpec;
use App\Models\TestProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequirementSpec>
 */
class RequirementSpecFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_project_id' => TestProject::factory(),
            'parent_id' => null,
            'name' => fake()->unique()->words(3, true),
            'doc_id' => fake()->unique()->bothify('SPEC-###'),
            'description' => null,
            'sort_order' => 0,
        ];
    }

    public function childOf(RequirementSpec $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'test_project_id' => $parent->test_project_id,
            'parent_id' => $parent->getKey(),
        ]);
    }
}
