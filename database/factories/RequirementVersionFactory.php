<?php

namespace Database\Factories;

use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use App\Models\Requirement;
use App\Models\RequirementVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequirementVersion>
 */
class RequirementVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requirement_id' => Requirement::factory(),
            'version' => 1,
            'scope' => fake()->sentence(),
            'status' => RequirementStatus::Draft,
            'type' => RequirementType::Feature,
            'expected_coverage' => 1,
            'is_open' => true,
            'author_id' => null,
            'updater_id' => null,
        ];
    }

    public function frozen(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_open' => false,
        ]);
    }

    public function version(int $version): static
    {
        return $this->state(fn (array $attributes): array => [
            'version' => $version,
        ]);
    }
}
