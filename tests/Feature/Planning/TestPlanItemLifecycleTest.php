<?php

namespace Tests\Feature\Planning;

use App\Actions\Executions\RecordExecution;
use App\Actions\TestPlanItems\LinkTestPlanItem;
use App\Actions\TestPlanItems\ReorderTestPlanItems;
use App\Actions\TestPlanItems\SetTestPlanItemUrgency;
use App\Actions\TestPlanItems\UnlinkTestPlanItem;
use App\Actions\TestPlanItems\UpdateLinkedTestCaseVersion;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\ExecutionStatus;
use App\Enums\TestCaseUrgency;
use App\Models\AuditEvent;
use App\Models\Build;
use App\Models\Platform;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TestPlanItemLifecycleTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_a_version_can_be_linked_to_a_plan_without_platforms(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create(['name' => 'Regression']);
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);
        $version = $this->versionIn($project);

        $item = app(LinkTestPlanItem::class)($user, $plan, $version);

        $this->assertSame($plan->id, $item->test_plan_id);
        $this->assertSame($version->id, $item->test_case_version_id);
        $this->assertNull($item->platform_id);
        $this->assertSame(1, $item->sort_order);
        $this->assertSame(TestCaseUrgency::Medium, $item->urgency);
        $this->assertSame($user->id, $item->author_id);

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::TestPlanItemLinked->value, $event->action);
        $this->assertSame(TestPlan::class, $event->subject_type);
        $this->assertSame($plan->id, $event->subject_id);
        $this->assertSame('Regression', $event->properties['name']);
        $this->assertSame($version->test_case_id, $event->properties['test_case_id']);
        $this->assertSame($version->testCase->name, $event->properties['test_case_name']);
        $this->assertSame(1, $event->properties['version']);
        $this->assertNull($event->properties['platform']);
        $this->assertSame('medium', $event->properties['urgency']);
    }

    public function test_a_later_link_is_appended_after_existing_items(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);
        TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
            'sort_order' => 3,
        ]);

        $item = app(LinkTestPlanItem::class)($user, $plan, $this->versionIn($project));

        $this->assertSame(4, $item->sort_order);
    }

    public function test_linking_with_a_platform_is_refused_when_the_plan_has_none(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);
        $platform = Platform::factory()->for($project)->create();

        try {
            app(LinkTestPlanItem::class)($user, $plan, $this->versionIn($project), $platform);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['This plan has no platforms. Link the case without one.'],
                $exception->errors()['platform'],
            );
        }

        $this->assertSame(0, TestPlanItem::query()->count());
    }

    public function test_linking_without_a_platform_is_refused_when_the_plan_uses_them(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);
        $plan->platforms()->attach(Platform::factory()->for($project)->create());

        try {
            app(LinkTestPlanItem::class)($user, $plan, $this->versionIn($project));
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['This plan uses platforms. Choose one.'],
                $exception->errors()['platform'],
            );
        }

        $this->assertSame(0, TestPlanItem::query()->count());
    }

    public function test_the_platform_must_already_be_assigned_to_the_plan(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);
        $assigned = Platform::factory()->for($project)->named('Chrome')->create();
        $other = Platform::factory()->for($project)->named('Safari')->create();
        $plan->platforms()->attach($assigned);

        try {
            app(LinkTestPlanItem::class)($user, $plan, $this->versionIn($project), $other);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['That platform is not assigned to this plan.'],
                $exception->errors()['platform'],
            );
        }

        $this->assertSame(0, TestPlanItem::query()->count());
    }

    public function test_a_version_from_another_project_is_refused(): void
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($ours)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);

        try {
            app(LinkTestPlanItem::class)($user, $plan, $this->versionIn($theirs));
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['That test case does not belong to this project.'],
                $exception->errors()['version'],
            );
        }

        $this->assertSame(0, TestPlanItem::query()->count());
    }

    public function test_linking_the_same_version_twice_on_the_same_platform_is_refused(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);
        $version = $this->versionIn($project);
        app(LinkTestPlanItem::class)($user, $plan, $version);

        try {
            app(LinkTestPlanItem::class)($user, $plan, $version);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['That version is already on this plan for that platform.'],
                $exception->errors()['version'],
            );
        }

        $this->assertSame(1, TestPlanItem::query()->count());
    }

    public function test_the_same_version_may_be_linked_once_per_plan_platform(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);
        $chrome = Platform::factory()->for($project)->named('Chrome')->create();
        $safari = Platform::factory()->for($project)->named('Safari')->create();
        $plan->platforms()->attach([$chrome->id, $safari->id]);
        $version = $this->versionIn($project);

        app(LinkTestPlanItem::class)($user, $plan, $version, $chrome);
        app(LinkTestPlanItem::class)($user, $plan, $version, $safari);

        $this->assertSame(2, $plan->items()->count());
    }

    public function test_unlinking_removes_the_item_and_is_recorded(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create(['name' => 'Regression']);
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);
        $version = $this->versionIn($project);
        $item = app(LinkTestPlanItem::class)($user, $plan, $version);

        app(UnlinkTestPlanItem::class)($user, $item);

        $this->assertSame(0, TestPlanItem::query()->count());

        $event = AuditEvent::query()
            ->where('action', AuditAction::TestPlanItemUnlinked->value)
            ->sole();

        $this->assertSame('Regression', $event->properties['name']);
        $this->assertSame($version->testCase->name, $event->properties['test_case_name']);
        $this->assertSame(1, $event->properties['version']);
    }

    public function test_unlinking_an_executed_item_needs_the_stronger_ability(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $linker = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases, Ability::ExecuteTests);

        app(RecordExecution::class)($linker, $item, $build, [
            'status' => ExecutionStatus::Passed,
            'steps' => [],
        ], true);

        $this->expectException(AuthorizationException::class);

        app(UnlinkTestPlanItem::class)($linker, $item);
    }

    public function test_an_executed_item_can_be_unlinked_with_the_stronger_ability(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan(
            $plan,
            Ability::PlanTestCases,
            Ability::ExecuteTests,
            Ability::UnlinkExecutedTestCases,
        );

        app(RecordExecution::class)($user, $item, $build, [
            'status' => ExecutionStatus::Passed,
            'steps' => [],
        ], true);

        app(UnlinkTestPlanItem::class)($user, $item);

        $this->assertSame(0, TestPlanItem::query()->count());
    }

    public function test_urgency_can_be_set_on_an_item(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::SetTestCaseUrgency);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);

        $updated = app(SetTestPlanItemUrgency::class)($user, $item, TestCaseUrgency::High);

        $this->assertSame(TestCaseUrgency::High, $updated->urgency);

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::TestPlanItemUrgencySet->value, $event->action);
        $this->assertSame('medium', $event->properties['urgency']['from']);
        $this->assertSame('high', $event->properties['urgency']['to']);
    }

    public function test_setting_the_same_urgency_records_nothing(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::SetTestCaseUrgency);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);

        app(SetTestPlanItemUrgency::class)($user, $item, TestCaseUrgency::Medium);

        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_a_linked_item_can_move_to_a_newer_version_of_the_same_case(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::UpdateLinkedTestCaseVersions);
        $case = $this->caseWithVersions($project, 2);
        $first = $case->versions->firstWhere('version', 1);
        $newer = $case->versions->firstWhere('version', 2);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $first->id,
        ]);

        $updated = app(UpdateLinkedTestCaseVersion::class)($user, $item);

        $this->assertTrue($updated->testCaseVersion->is($newer));

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::TestPlanItemVersionUpdated->value, $event->action);
        $this->assertSame(1, $event->properties['from_version']);
        $this->assertSame(2, $event->properties['to_version']);
    }

    public function test_moving_to_the_current_version_is_a_noop(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::UpdateLinkedTestCaseVersions);
        $version = $this->versionIn($project);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $version->id,
        ]);

        $updated = app(UpdateLinkedTestCaseVersion::class)($user, $item, $version);

        $this->assertTrue($updated->testCaseVersion->is($version));
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_the_item_cannot_move_to_an_older_version(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::UpdateLinkedTestCaseVersions);
        $case = $this->caseWithVersions($project, 2);
        $first = $case->versions->firstWhere('version', 1);
        $newer = $case->versions->firstWhere('version', 2);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $newer->id,
        ]);

        $this->expectException(ValidationException::class);

        app(UpdateLinkedTestCaseVersion::class)($user, $item, $first);
    }

    public function test_the_item_cannot_move_to_a_different_case(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::UpdateLinkedTestCaseVersions);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);

        $this->expectException(ValidationException::class);

        app(UpdateLinkedTestCaseVersion::class)($user, $item, $this->versionIn($project));
    }

    public function test_moving_is_refused_when_that_version_is_already_linked_on_the_same_platform(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::UpdateLinkedTestCaseVersions);
        $case = $this->caseWithVersions($project, 2);
        $first = $case->versions->firstWhere('version', 1);
        $newer = $case->versions->firstWhere('version', 2);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $first->id,
        ]);
        TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $newer->id,
        ]);

        try {
            app(UpdateLinkedTestCaseVersion::class)($user, $item, $newer);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['That version is already on this plan for that platform.'],
                $exception->errors()['version'],
            );
        }

        $this->assertSame($first->id, $item->fresh()->test_case_version_id);
    }

    public function test_items_can_be_reordered(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);
        $first = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
            'sort_order' => 1,
        ]);
        $second = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
            'sort_order' => 2,
        ]);

        app(ReorderTestPlanItems::class)($user, $plan, [$second->id, $first->id]);

        $this->assertSame(
            [$second->id, $first->id],
            $plan->items()->pluck('id')->all(),
        );
        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_reordering_refuses_a_list_that_is_not_exactly_the_plans_items(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);
        $other = TestPlanItem::factory()->create();

        try {
            app(ReorderTestPlanItems::class)($user, $plan, [$other->id]);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['The order must list every item on this plan exactly once.'],
                $exception->errors()['order'],
            );
        }

        $this->assertSame([$item->id], $plan->items()->pluck('id')->all());
    }

    private function caseWithVersions(TestProject $project, int $count): TestCaseModel
    {
        $suite = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($suite, 'testSuite')->create();

        for ($version = 1; $version <= $count; $version++) {
            TestCaseVersion::factory()->for($case, 'testCase')->version($version)->create();
        }

        return $case->load('versions');
    }
}
