<?php

namespace Database\Factories;

use App\Enums\TesterAssignmentStatus;
use App\Models\Build;
use App\Models\TesterAssignment;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TesterAssignment>
 */
class TesterAssignmentFactory extends Factory
{
    /**
     * The build is created on the item's plan so a factory-built row cannot
     * point at a build from another plan — the schema cannot enforce that,
     * because it is two hops.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_plan_item_id' => TestPlanItem::factory(),
            'build_id' => function (array $attributes): int {
                $item = TestPlanItem::query()->whereKey($attributes['test_plan_item_id'])->firstOrFail();

                return Build::factory()->for($item->testPlan)->create()->id;
            },
            'user_id' => User::factory(),
            'assigner_id' => null,
            'status' => TesterAssignmentStatus::Open,
            'deadline_at' => null,
        ];
    }

    public function urgent(): static
    {
        return $this->state(['status' => TesterAssignmentStatus::TodoUrgent]);
    }

    public function completed(): static
    {
        return $this->state(['status' => TesterAssignmentStatus::Completed]);
    }
}
