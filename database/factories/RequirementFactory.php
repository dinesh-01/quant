<?php

namespace Database\Factories;

use App\Models\Requirement;
use App\Models\RequirementSpec;
use App\Models\RequirementVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requirement>
 */
class RequirementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requirement_spec_id' => RequirementSpec::factory(),
            'test_project_id' => fn (array $attributes): int => RequirementSpec::query()
                ->whereKey($attributes['requirement_spec_id'])
                ->value('test_project_id'),
            'name' => fake()->unique()->sentence(4),
            'doc_id' => fake()->unique()->bothify('REQ-####'),
            'sort_order' => 0,
        ];
    }

    public function withVersion(): static
    {
        return $this->has(RequirementVersion::factory(), 'versions');
    }
}
