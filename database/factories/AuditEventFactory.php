<?php

namespace Database\Factories;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => fake()->randomElement(AuditAction::cases())->value,
            'properties' => null,
            'ip_address' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }

    /**
     * A console-driven event, which has no actor.
     */
    public function fromTheConsole(): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'ip_address' => null,
        ]);
    }
}
