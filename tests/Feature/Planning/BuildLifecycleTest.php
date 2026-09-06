<?php

namespace Tests\Feature\Planning;

use App\Actions\Builds\CreateBuild;
use App\Actions\Builds\DeleteBuild;
use App\Actions\Builds\UpdateBuild;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Actions\Executions\RecordExecution;
use App\Enums\ExecutionStatus;
use App\Models\AuditEvent;
use App\Models\Build;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BuildLifecycleTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_a_build_can_be_added_to_a_plan(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);

        $build = app(CreateBuild::class)($user, $plan, [
            'name' => '1.0.0',
            'notes' => 'First drop',
        ]);

        $this->assertSame('1.0.0', $build->name);
        $this->assertSame('First drop', $build->notes);
        $this->assertSame($plan->id, $build->test_plan_id);
        $this->assertSame($user->id, $build->author_id);
    }

    public function test_creating_a_build_is_recorded_in_the_audit_trail(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);

        $build = app(CreateBuild::class)($user, $plan, ['name' => '1.0.0']);

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::BuildCreated->value, $event->action);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame(Build::class, $event->subject_type);
        $this->assertSame($build->id, $event->subject_id);
        $this->assertSame('1.0.0', $event->properties['name']['to']);
    }

    public function test_two_builds_in_one_plan_cannot_share_a_name(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);
        Build::factory()->for($plan)->named('1.0.0')->create();

        try {
            app(CreateBuild::class)($user, $plan, ['name' => '1.0.0']);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['This plan already has a build with that name.'],
                $exception->errors()['name'],
            );
        }

        $this->assertSame(1, Build::query()->count());
    }

    public function test_a_name_is_trimmed_before_it_is_checked(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);
        Build::factory()->for($plan)->named('1.0.0')->create();

        $this->expectException(ValidationException::class);

        app(CreateBuild::class)($user, $plan, ['name' => '  1.0.0  ']);
    }

    public function test_an_empty_name_is_refused(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);

        $this->expectException(ValidationException::class);

        app(CreateBuild::class)($user, $plan, ['name' => '   ']);
    }

    public function test_two_plans_may_each_have_a_build_of_the_same_name(): void
    {
        $project = TestProject::factory()->create();
        $ours = TestPlan::factory()->for($project)->create();
        $theirs = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($ours, Ability::ManageBuilds);
        Build::factory()->for($theirs)->named('1.0.0')->create();

        app(CreateBuild::class)($user, $ours, ['name' => '1.0.0']);

        $this->assertSame(2, Build::query()->count());
    }

    public function test_a_build_can_be_renamed_and_closed(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);
        $build = Build::factory()->for($plan)->named('1.0.0-rc')->create();

        $updated = app(UpdateBuild::class)($user, $build, [
            'name' => '1.0.0',
            'is_open' => false,
        ]);

        $this->assertSame('1.0.0', $updated->name);
        $this->assertFalse($updated->is_open);
        $this->assertTrue($updated->is_active);
    }

    public function test_saving_a_build_unchanged_records_nothing(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);
        $build = Build::factory()->for($plan)->named('1.0.0')->create(['notes' => 'why']);

        app(UpdateBuild::class)($user, $build, ['name' => '1.0.0', 'notes' => 'why']);

        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_deleting_a_build_is_recorded_and_removes_it(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);
        $build = Build::factory()->for($plan)->named('1.0.0')->create();

        app(DeleteBuild::class)($user, $build);

        $this->assertSame(0, Build::query()->count());

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::BuildDeleted->value, $event->action);
        $this->assertSame('1.0.0', $event->properties['name']);
        $this->assertSame($plan->id, $event->properties['test_plan_id']);
    }

    public function test_a_build_with_executions_cannot_be_deleted(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds, Ability::ExecuteTests);

        app(RecordExecution::class)($user, $item, $build, [
            'status' => ExecutionStatus::Passed,
            'steps' => [],
        ], true);

        try {
            app(DeleteBuild::class)($user, $build);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('build', $exception->errors());
        }

        $this->assertTrue($build->fresh() !== null);
    }
}
