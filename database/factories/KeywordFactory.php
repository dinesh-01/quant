<?php

namespace Database\Factories;

use App\Models\Keyword;
use App\Models\TestProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Keyword>
 */
class KeywordFactory extends Factory
{
    /**
     * Names come from a unique word, because the table is unique per project
     * and a repeated `smoke` would fail the insert rather than the assertion
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
        ];
    }

    public function named(string $name): static
    {
        return $this->state(['name' => $name]);
    }
}
