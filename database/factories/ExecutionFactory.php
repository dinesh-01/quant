<?php

namespace Database\Factories;

use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\Execution;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Execution>
 */
class ExecutionFactory extends Factory
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
            'test_plan_item_id' => function (array $attributes): int {
                $plan = TestPlan::query()->whereKey($attributes['test_plan_id'])->firstOrFail();

                return TestPlanItem::factory()->for($plan, 'testPlan')->create()->id;
            },
            'test_case_version_id' => function (array $attributes): int {
                $item = TestPlanItem::query()->whereKey($attributes['test_plan_item_id'])->firstOrFail();

                return $item->test_case_version_id;
            },
            'version' => 1,
            'tester_id' => null,
            'status' => ExecutionStatus::NotRun,
            'notes' => null,
            'duration' => null,
            'is_draft' => true,
            'executed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_draft' => false,
            'status' => ExecutionStatus::Passed,
            'executed_at' => now(),
        ]);
    }
}
