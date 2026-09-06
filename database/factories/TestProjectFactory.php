<?php

namespace Database\Factories;

use App\Models\TestProject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TestProject>
 */
class TestProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'prefix' => Str::upper(Str::random(8)),
            'description' => null,
            'is_active' => true,
            'is_public' => true,
        ];
    }

    /**
     * Indicate that the project is only reachable by users who hold a role for
     * it.
     */
    public function restricted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_public' => false,
        ]);
    }
}
