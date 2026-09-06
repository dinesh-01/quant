<?php

namespace Tests\Feature\Planning;

use App\Enums\Ability;
use App\Enums\TesterAssignmentStatus;
use App\Models\Build;
use App\Models\TesterAssignment;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TesterAssignmentPageTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_contents_lists_assignments_and_assignable_testers(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $leader = $this->userWhoCanOnPlan($plan, Ability::AssignTesters, Ability::ExecuteTests);
        $tester = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);
        $build = Build::factory()->for($plan)->named('1.0.0')->create();
        TesterAssignment::factory()->create([
            'test_plan_item_id' => $item->id,
            'build_id' => $build->id,
            'user_id' => $tester->id,
            'status' => TesterAssignmentStatus::Open,
        ]);

        $this->actingAs($leader)
            ->get(route('plans.show', $plan))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('can.assignTesters', true)
                ->where('selectedBuildId', $build->id)
                ->has('testers', 2)
                ->has('assignments', 1)
                ->where('assignments.0.user_name', $tester->name)
                ->where('assignments.0.build_id', $build->id)
            );
    }

    public function test_a_tester_can_be_assigned_and_removed_through_http(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $leader = $this->userWhoCanOnPlan($plan, Ability::AssignTesters);
        $tester = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);
        $build = Build::factory()->for($plan)->create();

        $this->actingAs($leader)
            ->post(route('tester-assignments.store', $plan), [
                'test_plan_item_id' => $item->id,
                'build_id' => $build->id,
                'user_id' => $tester->id,
                'status' => 'todo',
            ])
            ->assertRedirect(route('plans.show', ['testPlan' => $plan, 'build' => $build->id]))
            ->assertSessionHasNoErrors();

        $assignment = TesterAssignment::query()->sole();

        $this->assertSame(TesterAssignmentStatus::Todo, $assignment->status);

        $this->actingAs($leader)
            ->put(route('tester-assignments.update', $assignment), [
                'status' => 'completed',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(TesterAssignmentStatus::Completed, $assignment->fresh()->status);

        $this->actingAs($leader)
            ->delete(route('tester-assignments.destroy', $assignment))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, TesterAssignment::query()->count());
    }

    public function test_assignments_can_be_copied_between_builds_through_http(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $leader = $this->userWhoCanOnPlan($plan, Ability::AssignTesters);
        $tester = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);
        $source = Build::factory()->for($plan)->named('old')->create();
        $target = Build::factory()->for($plan)->named('new')->create();
        TesterAssignment::factory()->create([
            'test_plan_item_id' => $item->id,
            'build_id' => $source->id,
            'user_id' => $tester->id,
        ]);

        $this->actingAs($leader)
            ->post(route('tester-assignments.copy', $plan), [
                'source_build_id' => $source->id,
                'target_build_id' => $target->id,
            ])
            ->assertRedirect(route('plans.show', ['testPlan' => $plan, 'build' => $target->id]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            TesterAssignment::query()
                ->where('build_id', $target->id)
                ->where('user_id', $tester->id)
                ->exists(),
        );
    }

    public function test_assignment_writes_are_forbidden_without_the_ability(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);
        $build = Build::factory()->for($plan)->create();

        $this->actingAs($user)
            ->post(route('tester-assignments.store', $plan), [
                'test_plan_item_id' => $item->id,
                'build_id' => $build->id,
                'user_id' => $user->id,
            ])
            ->assertForbidden();
    }
}
