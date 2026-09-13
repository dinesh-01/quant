<?php

namespace Tests\Feature\Api;

use App\Enums\Ability;
use App\Enums\ExecutionStatus;
use App\Jobs\ProcessReportedExecutions;
use App\Models\Build;
use App\Models\Execution;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Planning\InteractsWithPlanningRoles;
use Tests\TestCase;

class ExecutionBatchApiTest extends TestCase
{
    use AuthenticatesApiTokens;
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $plan = TestPlan::factory()->create();

        $this->postJson(route('api.v1.executions.batch', $plan), [
            'results' => [
                [
                    'test_plan_item_id' => 1,
                    'build_id' => 1,
                    'status' => ExecutionStatus::Passed->value,
                ],
            ],
        ])->assertUnauthorized();
    }

    public function test_accepts_a_batch_and_records_each_result(): void
    {
        [$plan, $first, $second, $build, $user] = $this->runnablePair();

        $this->apiAs($user)
            ->postJson(route('api.v1.executions.batch', $plan), [
                'results' => [
                    [
                        'test_plan_item_id' => $first->id,
                        'build_id' => $build->id,
                        'status' => ExecutionStatus::Passed->value,
                    ],
                    [
                        'full_external_id' => $second->testCaseVersion->testCase->fullExternalId(),
                        'build' => $build->name,
                        'status' => ExecutionStatus::Failed->value,
                        'notes' => 'Timeout',
                    ],
                ],
            ])
            ->assertAccepted()
            ->assertJsonPath('data.accepted', 2);

        $this->assertSame(2, Execution::query()->count());
        $this->assertSame(ExecutionStatus::Passed, Execution::query()->where('test_plan_item_id', $first->id)->value('status'));
        $this->assertSame(ExecutionStatus::Failed, Execution::query()->where('test_plan_item_id', $second->id)->value('status'));
    }

    public function test_dispatches_a_job_instead_of_writing_inline(): void
    {
        [$plan, $item, , $build, $user] = $this->runnablePair();

        Queue::fake([ProcessReportedExecutions::class]);

        $this->apiAs($user)
            ->postJson(route('api.v1.executions.batch', $plan), [
                'results' => [
                    [
                        'test_plan_item_id' => $item->id,
                        'build_id' => $build->id,
                        'status' => ExecutionStatus::Passed->value,
                    ],
                ],
            ])
            ->assertAccepted();

        $this->assertSame(0, Execution::query()->count());

        Queue::assertPushed(ProcessReportedExecutions::class, function (ProcessReportedExecutions $job) use ($user, $item, $build): bool {
            return $job->userId === $user->id
                && count($job->reports) === 1
                && $job->reports[0]['item_id'] === $item->id
                && $job->reports[0]['build_id'] === $build->id
                && $job->reports[0]['status'] === ExecutionStatus::Passed->value;
        });
    }

    public function test_returns_403_when_recording_without_execute(): void
    {
        [$plan, $item, , $build] = $this->runnablePair();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewExecutions);

        $this->apiAs($user)
            ->postJson(route('api.v1.executions.batch', $plan), [
                'results' => [
                    [
                        'test_plan_item_id' => $item->id,
                        'build_id' => $build->id,
                        'status' => ExecutionStatus::Passed->value,
                    ],
                ],
            ])
            ->assertForbidden();

        $this->assertSame(0, Execution::query()->count());
    }

    public function test_returns_422_when_the_item_is_on_another_plan(): void
    {
        [$plan, , , $build, $user] = $this->runnablePair();
        $foreign = TestPlanItem::factory()->create();

        $this->apiAs($user)
            ->postJson(route('api.v1.executions.batch', $plan), [
                'results' => [
                    [
                        'test_plan_item_id' => $foreign->id,
                        'build_id' => $build->id,
                        'status' => ExecutionStatus::Passed->value,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('results.0.test_plan_item_id')
            ->assertSee('That plan item is not on this plan.', false);

        $this->assertSame(0, Execution::query()->count());
    }

    public function test_returns_422_when_the_batch_is_empty(): void
    {
        [$plan, , , , $user] = $this->runnablePair();

        $this->apiAs($user)
            ->postJson(route('api.v1.executions.batch', $plan), [
                'results' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('results');
    }

    public function test_returns_422_when_the_batch_exceeds_100_results(): void
    {
        [$plan, $item, , $build, $user] = $this->runnablePair();

        $results = [];

        for ($i = 0; $i < 101; $i++) {
            $results[] = [
                'test_plan_item_id' => $item->id,
                'build_id' => $build->id,
                'status' => ExecutionStatus::Passed->value,
            ];
        }

        $this->apiAs($user)
            ->postJson(route('api.v1.executions.batch', $plan), [
                'results' => $results,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('results');
    }

    /**
     * @return array{0: TestPlan, 1: TestPlanItem, 2: TestPlanItem, 3: Build, 4: User}
     */
    private function runnablePair(): array
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $first = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $second = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $second->load('testCaseVersion.testCase.testProject');
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);

        return [$plan, $first, $second, $build, $user];
    }
}
