<?php

namespace Tests\Feature\Planning;

use App\Actions\Platforms\SyncPlanPlatforms;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Platform;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlanPlatformAssignmentTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_platforms_can_be_assigned_to_a_plan(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManagePlanPlatforms);
        $chrome = Platform::factory()->for($project)->named('Chrome')->create();
        $safari = Platform::factory()->for($project)->named('Safari')->create();

        app(SyncPlanPlatforms::class)($user, $plan, [$chrome->id, $safari->id]);

        $this->assertSame(['Chrome', 'Safari'], $plan->platforms()->orderBy('name')->pluck('name')->all());
    }

    public function test_submitting_the_picker_replaces_what_was_there(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManagePlanPlatforms);
        $kept = Platform::factory()->for($project)->named('Chrome')->create();
        $dropped = Platform::factory()->for($project)->named('Safari')->create();
        $plan->platforms()->attach([$kept->id, $dropped->id]);

        app(SyncPlanPlatforms::class)($user, $plan, [$kept->id]);

        $this->assertSame(['Chrome'], $plan->platforms()->pluck('name')->all());
    }

    public function test_a_platform_from_another_project_is_refused(): void
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($ours)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManagePlanPlatforms);
        $foreign = Platform::factory()->for($theirs)->create();

        try {
            app(SyncPlanPlatforms::class)($user, $plan, [$foreign->id]);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['One of those platforms does not belong to this test project.'],
                $exception->errors()['platforms'],
            );
        }

        $this->assertSame(0, $plan->platforms()->count());
    }

    public function test_an_execution_disabled_platform_cannot_be_assigned(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManagePlanPlatforms);
        $platform = Platform::factory()->for($project)->designOnly()->create();

        $this->expectException(ValidationException::class);

        app(SyncPlanPlatforms::class)($user, $plan, [$platform->id]);
    }

    public function test_a_closed_platform_cannot_be_added_to_a_plan(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManagePlanPlatforms);
        $platform = Platform::factory()->for($project)->closed()->create();

        $this->expectException(ValidationException::class);

        app(SyncPlanPlatforms::class)($user, $plan, [$platform->id]);
    }

    public function test_an_already_assigned_closed_platform_may_stay(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManagePlanPlatforms);
        $closed = Platform::factory()->for($project)->named('Legacy')->closed()->create();
        $open = Platform::factory()->for($project)->named('Chrome')->create();
        $plan->platforms()->attach($closed);

        app(SyncPlanPlatforms::class)($user, $plan, [$closed->id, $open->id]);

        $this->assertSame(['Chrome', 'Legacy'], $plan->platforms()->orderBy('name')->pluck('name')->all());
    }

    public function test_adding_the_first_platform_is_refused_while_null_platform_items_exist(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManagePlanPlatforms);
        $platform = Platform::factory()->for($project)->create();
        TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);

        try {
            app(SyncPlanPlatforms::class)($user, $plan, [$platform->id]);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['This plan has cases linked without a platform. Unlink them before adding platforms.'],
                $exception->errors()['platforms'],
            );
        }

        $this->assertSame(0, $plan->platforms()->count());
        $this->assertSame(1, $plan->items()->count());
    }

    public function test_removing_a_plan_platform_deletes_the_items_on_it(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManagePlanPlatforms);
        $chrome = Platform::factory()->for($project)->named('Chrome')->create();
        $safari = Platform::factory()->for($project)->named('Safari')->create();
        $plan->platforms()->attach([$chrome->id, $safari->id]);
        $version = $this->versionIn($project);
        TestPlanItem::factory()->for($plan)->onPlatform($chrome->id)->create([
            'test_case_version_id' => $version->id,
        ]);
        $kept = TestPlanItem::factory()->for($plan)->onPlatform($safari->id)->create([
            'test_case_version_id' => $version->id,
        ]);

        app(SyncPlanPlatforms::class)($user, $plan, [$safari->id]);

        $this->assertSame(['Safari'], $plan->platforms()->pluck('name')->all());
        $this->assertSame([$kept->id], $plan->items()->pluck('id')->all());
    }

    public function test_a_change_records_what_was_added_removed_and_how_many_items_went(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create(['name' => 'Regression']);
        $user = $this->userWhoCanOnPlan($plan, Ability::ManagePlanPlatforms);
        $added = Platform::factory()->for($project)->named('Chrome')->create();
        $removed = Platform::factory()->for($project)->named('Safari')->create();
        $plan->platforms()->attach($removed);
        TestPlanItem::factory()->for($plan)->onPlatform($removed->id)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);

        app(SyncPlanPlatforms::class)($user, $plan, [$added->id]);

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::PlanPlatformsChanged->value, $event->action);
        $this->assertSame(TestPlan::class, $event->subject_type);
        $this->assertSame($plan->id, $event->subject_id);
        $this->assertSame('Regression', $event->properties['name']);
        $this->assertSame(['Chrome'], $event->properties['added']);
        $this->assertSame(['Safari'], $event->properties['removed']);
        $this->assertSame(1, $event->properties['items_removed']);
    }

    public function test_resubmitting_the_same_platforms_records_nothing(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManagePlanPlatforms);
        $platform = Platform::factory()->for($project)->create();
        $plan->platforms()->attach($platform);

        app(SyncPlanPlatforms::class)($user, $plan, [$platform->id]);

        $this->assertSame(0, AuditEvent::query()->count());
    }
}
