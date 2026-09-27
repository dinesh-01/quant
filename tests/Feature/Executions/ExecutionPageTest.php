<?php

namespace Tests\Feature\Executions;

use App\Actions\Executions\RecordExecution;
use App\Actions\TesterAssignments\AssignTester;
use App\Actions\TestSpecification\CreateTestCase;
use App\Actions\TestSpecification\CreateTestCaseStep;
use App\Actions\TestSpecification\CreateTestSuite;
use App\Enums\Ability;
use App\Enums\ExecutionStatus;
use App\Enums\TestCaseExecutionType;
use App\Models\Build;
use App\Models\Role;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Planning\InteractsWithPlanningRoles;
use Tests\TestCase;

class ExecutionPageTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_the_navigator_lists_plan_items_for_a_build(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create(['name' => 'Sprint']);
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create(['name' => '1.0']);
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests, Ability::ViewExecutions);

        $this->actingAs($user)
            ->get(route('executions.index', ['testPlan' => $plan, 'build' => $build->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('executions/index')
                ->where('plan.name', 'Sprint')
                ->where('plan.external_id', $plan->loadMissing('testProject')->fullExternalId())
                ->where('selectedBuildId', $build->id)
                ->has('items', 1)
                ->where('items.0.id', $item->id)
                ->where('items.0.priority', 'medium')
                ->where('items.0.assigned_to_viewer', false)
                ->has('items.0.suite')
                ->has('items.0.keywords')
                ->has('counts')
            );
    }

    public function test_a_run_can_be_completed_through_http(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests, Ability::ViewExecutions);

        $this->actingAs($user)
            ->post(route('executions.store', $item), [
                'build_id' => $build->id,
                'status' => ExecutionStatus::Passed->value,
                'complete' => '1',
            ])
            ->assertRedirect(route('executions.index', [
                'testPlan' => $plan,
                'build' => $build->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('executions', [
            'test_plan_item_id' => $item->id,
            'build_id' => $build->id,
            'is_draft' => false,
            'status' => ExecutionStatus::Passed->value,
        ]);

        $this->actingAs($user)
            ->get(route('executions.show', ['testPlan' => $plan, 'testPlanItem' => $item, 'build' => $build->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('executions/show')
                ->has('history', 1)
                ->where('history.0.status', 'passed')
                ->where('draft', null)
                ->where('item.priority', 'medium')
                ->has('item.keywords')
                ->where('item.assigned_to_viewer', false)
                ->has('item.test_case_id')
                ->has('builds')
                ->where('openIssue', false)
                ->has('items')
                ->has('counts')
            );
    }

    public function test_the_run_pane_expands_ghost_markup_in_the_step(): void
    {
        $project = TestProject::factory()->create(['prefix' => 'PAY']);
        $author = $this->userWhoCan(
            $project,
            Ability::ManageTestCases,
            Ability::ViewTestCases,
            Ability::CreateTestPlans,
            Ability::PlanTestCases,
            Ability::ExecuteTests,
            Ability::ViewExecutions,
        );
        $suite = app(CreateTestSuite::class)($author, $project, null, ['name' => 'Shared']);
        $source = app(CreateTestCase::class)($author, $suite, [
            'name' => 'Sign in',
            'summary' => '<p>Open the app</p>',
        ]);
        app(CreateTestCaseStep::class)($author, $source->latestVersion, [
            'actions' => '<p>Enter the password</p>',
            'expected_results' => '<p>Home</p>',
            'execution_type' => TestCaseExecutionType::Manual,
        ]);
        $runner = app(CreateTestCase::class)($author, $suite, [
            'name' => 'Reuse sign in',
        ]);
        $version = $runner->latestVersion;
        $version->summary = '[ghost]"TestCase":"'.$source->fullExternalId().'"[/ghost]';
        $version->save();
        app(CreateTestCaseStep::class)($author, $version, [
            'actions' => '[ghost]"TestCase":"'.$source->fullExternalId().'","Step":"1"[/ghost]',
            'execution_type' => TestCaseExecutionType::Manual,
        ]);

        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create([
            'test_case_version_id' => $version->id,
        ]);
        $build = Build::factory()->for($plan, 'testPlan')->create();

        $this->actingAs($author)
            ->get(route('executions.show', [
                'testPlan' => $plan,
                'testPlanItem' => $item,
                'build' => $build->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('item.summary', '<p>Open the app</p>')
                ->where('item.steps.0.actions', '<p>Enter the password</p>')
            );
    }

    public function test_a_super_admin_sees_every_plan_item_on_the_run_list(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = User::factory()->for(Role::factory()->superAdmin())->create();

        $this->actingAs($user)
            ->get(route('executions.index', ['testPlan' => $plan, 'build' => $build->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items', 1)
                ->where('items.0.id', $item->id)
            );
    }

    public function test_an_assigned_only_tester_sees_only_their_items(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $assigned = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $leader = $this->userWhoCanOnPlan($plan, Ability::AssignTesters, Ability::ExecuteTests);
        $tester = $this->userWhoCanOnPlan(
            $plan,
            Ability::ExecuteTests,
            Ability::ViewExecutions,
            Ability::ExecuteOnlyAssignedTestCases,
        );

        app(AssignTester::class)($leader, $assigned, $build, $tester);

        $this->actingAs($tester)
            ->get(route('executions.index', ['testPlan' => $plan, 'build' => $build->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items', 1)
                ->where('items.0.id', $assigned->id)
                ->where('items.0.assigned_to_viewer', true)
            );
    }

    public function test_viewing_the_navigator_requires_execute_or_view(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewTestCases);

        $this->actingAs($user)
            ->get(route('executions.index', $plan))
            ->assertForbidden();
    }

    public function test_opening_a_plan_with_start_opens_the_first_untested_case(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $done = TestPlanItem::factory()->for($plan, 'testPlan')->create(['sort_order' => 1]);
        $next = TestPlanItem::factory()->for($plan, 'testPlan')->create(['sort_order' => 2]);
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests, Ability::ViewExecutions);

        app(RecordExecution::class)($user, $done, $build, [
            'status' => ExecutionStatus::Passed,
            'steps' => [],
        ], true);

        $this->actingAs($user)
            ->get(route('executions.index', ['testPlan' => $plan, 'build' => $build->id, 'start' => 1]))
            ->assertRedirect(route('executions.show', [
                'testPlan' => $plan,
                'testPlanItem' => $next,
                'build' => $build->id,
            ]));
    }

    public function test_completing_a_passed_run_opens_the_next_untested_case(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $first = TestPlanItem::factory()->for($plan, 'testPlan')->create(['sort_order' => 1]);
        $second = TestPlanItem::factory()->for($plan, 'testPlan')->create(['sort_order' => 2]);
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests, Ability::ViewExecutions);

        $this->actingAs($user)
            ->post(route('executions.store', $first), [
                'build_id' => $build->id,
                'status' => ExecutionStatus::Passed->value,
                'complete' => '1',
            ])
            ->assertRedirect(route('executions.show', [
                'testPlan' => $plan,
                'testPlanItem' => $second,
                'build' => $build->id,
            ]));

        $this->assertDatabaseHas('executions', [
            'test_plan_item_id' => $first->id,
            'build_id' => $build->id,
            'is_draft' => false,
            'status' => ExecutionStatus::Passed->value,
        ]);
    }

    public function test_completing_a_failed_run_stays_so_an_issue_can_be_linked(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests, Ability::ViewExecutions);

        $this->actingAs($user)
            ->post(route('executions.store', $item), [
                'build_id' => $build->id,
                'status' => ExecutionStatus::Failed->value,
                'complete' => '1',
            ])
            ->assertRedirect(route('executions.show', [
                'testPlan' => $plan,
                'testPlanItem' => $item,
                'build' => $build->id,
                'issue' => 1,
            ]));

        $this->assertDatabaseHas('executions', [
            'test_plan_item_id' => $item->id,
            'is_draft' => false,
            'status' => ExecutionStatus::Failed->value,
        ]);
    }

    public function test_skipping_a_case_opens_the_next_untested_case_without_recording(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $first = TestPlanItem::factory()->for($plan, 'testPlan')->create(['sort_order' => 1]);
        $second = TestPlanItem::factory()->for($plan, 'testPlan')->create(['sort_order' => 2]);
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests, Ability::ViewExecutions);

        $this->actingAs($user)
            ->post(route('executions.store', $first), [
                'build_id' => $build->id,
                'status' => ExecutionStatus::NotRun->value,
                'complete' => '1',
            ])
            ->assertRedirect(route('executions.show', [
                'testPlan' => $plan,
                'testPlanItem' => $second,
                'build' => $build->id,
            ]));

        $this->assertDatabaseMissing('executions', [
            'test_plan_item_id' => $first->id,
        ]);
    }

    public function test_skipping_a_case_requires_execute(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ViewExecutions);

        $this->actingAs($user)
            ->post(route('executions.store', $item), [
                'build_id' => $build->id,
                'status' => ExecutionStatus::NotRun->value,
                'complete' => '1',
            ])
            ->assertForbidden();
    }

    public function test_saving_a_draft_stays_on_the_same_case(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests, Ability::ViewExecutions);
        $url = route('executions.show', [
            'testPlan' => $plan,
            'testPlanItem' => $item,
            'build' => $build->id,
        ]);

        $this->actingAs($user)
            ->from($url)
            ->post(route('executions.store', $item), [
                'build_id' => $build->id,
                'status' => ExecutionStatus::Passed->value,
            ])
            ->assertRedirect($url);

        $this->assertDatabaseHas('executions', [
            'test_plan_item_id' => $item->id,
            'is_draft' => true,
            'status' => ExecutionStatus::Passed->value,
        ]);
    }
}
