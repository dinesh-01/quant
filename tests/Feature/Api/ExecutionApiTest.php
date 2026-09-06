<?php

namespace Tests\Feature\Api;

use App\Enums\Ability;
use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\Execution;
use App\Models\Platform;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Planning\InteractsWithPlanningRoles;
use Tests\TestCase;

class ExecutionApiTest extends TestCase
{
    use AuthenticatesApiTokens;
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_records_an_execution_by_plan_item_id(): void
    {
        [$plan, $item, $build, $user] = $this->runnable();

        $this->apiAs($user)
            ->postJson(route('api.v1.executions.store', $plan), [
                'test_plan_item_id' => $item->id,
                'build_id' => $build->id,
                'status' => ExecutionStatus::Passed->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'passed')
            ->assertJsonPath('data.is_draft', false)
            ->assertJsonPath('data.test_plan_item_id', $item->id);

        $this->assertSame(1, Execution::query()->count());
    }

    public function test_records_an_execution_by_external_id_and_build_name(): void
    {
        [$plan, $item, $build, $user] = $this->runnable();
        $item->load('testCaseVersion.testCase.testProject');

        $this->apiAs($user)
            ->postJson(route('api.v1.executions.store', $plan), [
                'full_external_id' => $item->testCaseVersion->testCase->fullExternalId(),
                'build' => $build->name,
                'status' => ExecutionStatus::Failed->value,
                'notes' => 'Timeout',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.notes', 'Timeout');
    }

    public function test_returns_403_when_recording_without_execute(): void
    {
        [$plan, $item, $build] = $this->runnable();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewExecutions);

        $this->apiAs($user)
            ->postJson(route('api.v1.executions.store', $plan), [
                'test_plan_item_id' => $item->id,
                'build_id' => $build->id,
                'status' => ExecutionStatus::Passed->value,
            ])
            ->assertForbidden();
    }

    public function test_returns_422_when_the_item_is_on_another_plan(): void
    {
        [$plan, , $build, $user] = $this->runnable();
        $foreign = TestPlanItem::factory()->create();

        $this->apiAs($user)
            ->postJson(route('api.v1.executions.store', $plan), [
                'test_plan_item_id' => $foreign->id,
                'build_id' => $build->id,
                'status' => ExecutionStatus::Passed->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('test_plan_item_id');
    }

    public function test_requires_a_platform_when_the_case_is_on_more_than_one(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $version = $this->versionIn($project);
        $chrome = Platform::factory()->for($project)->named('Chrome')->create();
        $firefox = Platform::factory()->for($project)->named('Firefox')->create();
        TestPlanItem::factory()->for($plan, 'testPlan')->for($version, 'testCaseVersion')->onPlatform($chrome->id)->create();
        TestPlanItem::factory()->for($plan, 'testPlan')->for($version, 'testCaseVersion')->onPlatform($firefox->id)->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);

        $this->apiAs($user)
            ->postJson(route('api.v1.executions.store', $plan), [
                'full_external_id' => $version->testCase->fullExternalId(),
                'build_id' => $build->id,
                'status' => ExecutionStatus::Passed->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('platform');

        $this->apiAs($user)
            ->postJson(route('api.v1.executions.store', $plan), [
                'full_external_id' => $version->testCase->fullExternalId(),
                'platform' => 'Firefox',
                'build_id' => $build->id,
                'status' => ExecutionStatus::Passed->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'passed');
    }

    public function test_lists_the_plan_cases_the_caller_can_read(): void
    {
        [$plan, $item, , $user] = $this->runnable();
        $item->load('testCaseVersion.testCase.testProject');

        $this->apiAs($user)
            ->getJson(route('api.v1.plans.cases.index', $plan))
            ->assertOk()
            ->assertJsonPath('data.0.id', $item->id)
            ->assertJsonPath('data.0.full_external_id', $item->testCaseVersion->testCase->fullExternalId())
            ->assertJsonPath('meta.has_more', false);
    }

    /**
     * @return array{0: TestPlan, 1: TestPlanItem, 2: Build, 3: User}
     */
    private function runnable(): array
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);

        return [$plan, $item, $build, $user];
    }
}
