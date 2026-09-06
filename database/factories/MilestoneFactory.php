<?php

namespace Database\Factories;

use App\Models\Milestone;
use App\Models\TestPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 */
class MilestoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_plan_id' => TestPlan::factory(),
            'name' => fake()->unique()->bothify('M-###'),
            'target_date' => now()->addWeeks(2)->toDateString(),
            'start_date' => now()->toDateString(),
            'high_percent' => 100,
            'medium_percent' => 80,
            'low_percent' => 50,
        ];
    }
}
