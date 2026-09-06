<?php

namespace Database\Factories;

use App\Models\Platform;
use App\Models\TestProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Platform>
 */
class PlatformFactory extends Factory
{
    /**
     * Names come from a unique word, because the table is unique per project
     * and a repeated `chrome` would fail the insert rather than the assertion
     * the test was making.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_project_id' => TestProject::factory(),
            'name' => $this->faker->unique()->word(),
            'notes' => null,
            'enable_on_design' => true,
            'enable_on_execution' => true,
            'is_open' => true,
        ];
    }

    public function named(string $name): static
    {
        return $this->state(['name' => $name]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_open' => false,
        ]);
    }

    /**
     * A platform that may be used on a plan, but not tagged on a version.
     */
    public function executionOnly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'enable_on_design' => false,
        ]);
    }

    /**
     * A platform that may be tagged on a version, but not added to a plan.
     */
    public function designOnly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'enable_on_execution' => false,
        ]);
    }
}
