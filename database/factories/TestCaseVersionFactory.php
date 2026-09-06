<?php

namespace Database\Factories;

use App\Enums\TestCaseExecutionType;
use App\Enums\TestCaseImportance;
use App\Enums\TestCaseStatus;
use App\Models\TestCase;
use App\Models\TestCaseVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestCaseVersion>
 */
class TestCaseVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_case_id' => TestCase::factory(),
            'version' => 1,
            'status' => TestCaseStatus::Draft,
            'summary' => fake()->sentence(),
            'preconditions' => null,
            'importance' => TestCaseImportance::Medium,
            'execution_type' => TestCaseExecutionType::Manual,
            'estimated_duration' => null,
            'is_open' => true,
            'author_id' => null,
            'updater_id' => null,
        ];
    }

    /**
     * Close the version to further editing.
     */
    public function frozen(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_open' => false,
        ]);
    }

    /**
     * Give the version a specific number within its case.
     */
    public function version(int $version): static
    {
        return $this->state(fn (array $attributes): array => [
            'version' => $version,
        ]);
    }
}
