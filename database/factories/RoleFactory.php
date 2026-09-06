<?php

namespace Database\Factories;

use App\Enums\Ability;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'description' => null,
            'abilities' => [],
            'is_super_admin' => false,
            'is_default' => false,
        ];
    }

    /**
     * Grant the role exactly the given abilities.
     */
    public function granting(Ability ...$abilities): static
    {
        return $this->state(fn (array $attributes): array => [
            'abilities' => $abilities,
        ]);
    }

    /**
     * Indicate that the role passes every ability check.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_super_admin' => true,
        ]);
    }

    /**
     * Indicate that the role is given to newly registered users.
     */
    public function asDefault(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }
}
