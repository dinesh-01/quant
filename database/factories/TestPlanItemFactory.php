<?php

namespace Database\Factories;

use App\Enums\TestCaseUrgency;
use App\Models\TestCase;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestSuite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestPlanItem>
 */
class TestPlanItemFactory extends Factory
{
    /**
     * The version is created inside the plan's project, so a factory-built
     * item cannot violate the "same project" rule that Slice B will enforce
     * in validation — the schema cannot, because that is two hops.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_plan_id' => TestPlan::factory(),
            'test_case_version_id' => function (array $attributes): int {
                $plan = TestPlan::query()->whereKey($attributes['test_plan_id'])->firstOrFail();
                $suite = TestSuite::factory()->for($plan->testProject)->create();
                $case = TestCase::factory()->for($suite, 'testSuite')->create();

                return TestCaseVersion::factory()->for($case, 'testCase')->create()->id;
            },
            'platform_id' => null,
            'sort_order' => 0,
            'urgency' => TestCaseUrgency::Medium,
            'author_id' => null,
        ];
    }

    public function onPlatform(int $platformId): static
    {
        return $this->state(['platform_id' => $platformId]);
    }

    public function urgent(): static
    {
        return $this->state(['urgency' => TestCaseUrgency::High]);
    }
}
