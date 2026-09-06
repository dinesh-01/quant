<?php

namespace Database\Factories;

use App\Enums\TestCaseExecutionType;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestCaseStep>
 */
class TestCaseStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_case_version_id' => TestCaseVersion::factory(),
            'sort_order' => 1,
            'actions' => fake()->sentence(),
            'expected_results' => fake()->sentence(),
            'execution_type' => TestCaseExecutionType::Manual,
        ];
    }

    /**
     * Place the step at a specific position within its version.
     */
    public function at(int $position): static
    {
        return $this->state(fn (array $attributes): array => [
            'sort_order' => $position,
        ]);
    }
}
