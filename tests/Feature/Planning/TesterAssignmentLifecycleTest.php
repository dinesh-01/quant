<?php

namespace Tests\Feature\Planning;

use App\Actions\TesterAssignments\AssignTester;
use App\Actions\TesterAssignments\CopyTesterAssignments;
use App\Actions\TesterAssignments\UnassignTester;
use App\Actions\TesterAssignments\UpdateTesterAssignment;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\TesterAssignmentStatus;
use App\Models\AuditEvent;
use App\Models\Build;
use App\Models\TesterAssignment;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TesterAssignmentLifecycleTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_a_tester_who_can_execute_is_assigned_to_a_case_on_a_build(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create(['name' => 'Release']);
        $leader = $this->userWhoCanOnPlan($plan, Ability::AssignTesters);
        $tester = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);
        $build = Build::factory()->for($plan)->named('1.0.0')->create();

        $assignment = app(AssignTester::class)($leader, $item, $build, $tester);

        $this->assertSame($item->id, $assignment->test_plan_item_id);
        $this->assertSame($build->id, $assignment->build_id);
        $this->assertSame($tester->id, $assignment->user_id);
        $this->assertSame($leader->id, $assignment->assigner_id);
        $this->assertSame(TesterAssignmentStatus::Open, $assignment->status);

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::TesterAssigned->value, $event->action);
        $this->assertSame($plan->id, $event->subject_id);
        $this->assertSame('Release', $event->properties['name']);
        $this->assertSame('1.0.0', $event->properties['build']);
        $this->assertSame($tester->id, $event->properties['tester_id']);
        $this->assertSame($tester->name, $event->properties['tester']);
    }

    public function test_the_same_tester_cannot_be_assigned_twice_on_the_same_build(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $leader = $this->userWhoCanOnPlan($plan, Ability::AssignTesters);
        $tester = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);
        $build = Build::factory()->for($plan)->create();
        app(AssignTester::class)($leader, $item, $build, $tester);

        try {
            app(AssignTester::class)($leader, $item, $build, $tester);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['That person is already assigned to this case on this build.'],
                $exception->errors()['tester'],
            );
        }

        $this->assertSame(1, TesterAssignment::query()->count());
    }

    public function test_a_person_who_cannot_execute_is_refused(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $leader = $this->userWhoCanOnPlan($plan, Ability::AssignTesters);
        $guest = $this->userWhoCanOnPlan($plan, Ability::ViewTestCases);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);
        $build = Build::factory()->for($plan)->create();

        try {
            app(AssignTester::class)($leader, $item, $build, $guest);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['That person cannot execute tests on this plan.'],
                $exception->errors()['tester'],
            );
        }
    }

    public function test_a_build_from_another_plan_is_refused(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $other = TestPlan::factory()->for($project)->create();
        $leader = $this->userWhoCanOnPlan($plan, Ability::AssignTesters);
        $tester = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);
        $build = Build::factory()->for($other)->create();

        try {
            app(AssignTester::class)($leader, $item, $build, $tester);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['That build does not belong to this plan.'],
                $exception->errors()['build'],
            );
        }
    }

    public function test_assigning_is_forbidden_without_the_ability(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);
        $build = Build::factory()->for($plan)->create();

        $this->expectException(AuthorizationException::class);

        app(AssignTester::class)($user, $item, $build, $user);
    }

    public function test_an_assignment_can_be_updated_and_removed(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $leader = $this->userWhoCanOnPlan($plan, Ability::AssignTesters);
        $tester = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);
        $build = Build::factory()->for($plan)->create();
        $assignment = app(AssignTester::class)($leader, $item, $build, $tester);

        $updated = app(UpdateTesterAssignment::class)($leader, $assignment, [
            'status' => TesterAssignmentStatus::TodoUrgent,
            'deadline_at' => now()->addDay(),
        ]);

        $this->assertSame(TesterAssignmentStatus::TodoUrgent, $updated->status);
        $this->assertNotNull($updated->deadline_at);

        app(UnassignTester::class)($leader, $assignment);

        $this->assertSame(0, TesterAssignment::query()->count());
        $this->assertTrue(
            AuditEvent::query()->where('action', AuditAction::TesterUnassigned->value)->exists(),
        );
    }

    public function test_assignments_copy_onto_another_build_and_skip_existing_slots(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $leader = $this->userWhoCanOnPlan($plan, Ability::AssignTesters);
        $tester = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);
        $other = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);
        $source = Build::factory()->for($plan)->named('1.0')->create();
        $target = Build::factory()->for($plan)->named('1.1')->create();

        app(AssignTester::class)($leader, $item, $source, $tester, TesterAssignmentStatus::Completed);
        app(AssignTester::class)($leader, $item, $source, $other);
        app(AssignTester::class)($leader, $item, $target, $tester);

        $copied = app(CopyTesterAssignments::class)($leader, $source, $target);

        $this->assertSame(1, $copied);
        $this->assertSame(4, TesterAssignment::query()->count());

        $new = TesterAssignment::query()
            ->where('build_id', $target->id)
            ->where('user_id', $other->id)
            ->sole();

        $this->assertSame(TesterAssignmentStatus::Open, $new->status);
        $this->assertSame(TesterAssignmentStatus::Open, TesterAssignment::query()->whereKey(
            TesterAssignment::query()->where('build_id', $target->id)->where('user_id', $tester->id)->value('id'),
        )->sole()->status);
    }

    public function test_copying_onto_the_same_build_is_refused(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $leader = $this->userWhoCanOnPlan($plan, Ability::AssignTesters);
        $build = Build::factory()->for($plan)->create();

        try {
            app(CopyTesterAssignments::class)($leader, $build, $build);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Choose a different build to copy onto.'],
                $exception->errors()['target_build'],
            );
        }
    }
}
