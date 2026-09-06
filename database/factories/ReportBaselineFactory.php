<?php

namespace Database\Factories;

use App\Models\Build;
use App\Models\ReportBaseline;
use App\Models\TestPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportBaseline>
 */
class ReportBaselineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_plan_id' => TestPlan::factory(),
            'build_id' => function (array $attributes): int {
                $plan = TestPlan::query()->whereKey($attributes['test_plan_id'])->firstOrFail();

                return Build::factory()->for($plan, 'testPlan')->create()->id;
            },
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'total' => 0,
            'counts' => [
                'passed' => 0,
                'failed' => 0,
                'blocked' => 0,
                'not_run' => 0,
            ],
            'items' => [],
        ];
    }
}
