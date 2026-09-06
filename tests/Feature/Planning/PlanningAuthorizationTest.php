<?php

namespace Tests\Feature\Planning;

use App\Actions\Builds\CreateBuild;
use App\Actions\Builds\DeleteBuild;
use App\Actions\Builds\UpdateBuild;
use App\Actions\Platforms\CreatePlatform;
use App\Actions\Platforms\DeletePlatform;
use App\Actions\Platforms\SyncPlanPlatforms;
use App\Actions\Platforms\SyncVersionPlatforms;
use App\Actions\Platforms\UpdatePlatform;
use App\Actions\TestPlanItems\LinkTestPlanItem;
use App\Actions\TestPlanItems\ReorderTestPlanItems;
use App\Actions\TestPlanItems\SetTestPlanItemUrgency;
use App\Actions\TestPlanItems\UnlinkTestPlanItem;
use App\Actions\TestPlanItems\UpdateLinkedTestCaseVersion;
use App\Enums\Ability;
use App\Enums\TestCaseUrgency;
use App\Models\Build;
use App\Models\Platform;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every planning write authorizes inside the action rather than in a
 * controller, so the check cannot be skipped by a caller that forgets it.
 * These tests cover the denied path for each of them.
 *
 * CopyPlatformAssignments is absent on purpose: it is an internal
 * collaborator that no request reaches, and the copy action that calls it
 * authorizes both ends.
 */
class PlanningAuthorizationTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_creating_a_platform_requires_managing_platforms(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlanPlatforms);

        $this->expectException(AuthorizationException::class);

        app(CreatePlatform::class)($user, $project, ['name' => 'Chrome']);
    }

    public function test_updating_a_platform_requires_managing_platforms(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlanPlatforms);
        $platform = Platform::factory()->for($project)->create();

        $this->expectException(AuthorizationException::class);

        app(UpdatePlatform::class)($user, $platform, ['name' => 'Renamed']);
    }

    public function test_deleting_a_platform_requires_managing_platforms(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlanPlatforms);
        $platform = Platform::factory()->for($project)->create();

        $this->expectException(AuthorizationException::class);

        app(DeletePlatform::class)($user, $platform);
    }

    public function test_managing_one_projects_platforms_does_not_reach_another(): void
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::ManagePlatforms);
        $platform = Platform::factory()->for($theirs)->create();

        $this->expectException(AuthorizationException::class);

        app(DeletePlatform::class)($user, $platform);
    }

    /**
     * Curating the vocabulary and choosing which of it a plan uses are
     * different jobs. Legacy's split was the same intent.
     */
    public function test_managing_platforms_does_not_let_someone_assign_them_to_a_plan(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCan($project, Ability::ManagePlatforms);
        $platform = Platform::factory()->for($project)->create();

        $this->expectException(AuthorizationException::class);

        app(SyncPlanPlatforms::class)($user, $plan, [$platform->id]);
    }

    public function test_assigning_plan_platforms_requires_the_plan_ability(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCan($project, Ability::PlanTestCases);
        $platform = Platform::factory()->for($project)->create();

        $this->expectException(AuthorizationException::class);

        app(SyncPlanPlatforms::class)($user, $plan, [$platform->id]);
    }

    public function test_tagging_a_version_requires_managing_test_cases_not_platforms(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlatforms);
        $version = $this->versionIn($project);
        $platform = Platform::factory()->for($project)->create();

        $this->expectException(AuthorizationException::class);

        app(SyncVersionPlatforms::class)($user, $version, [$platform->id]);
    }

    public function test_creating_a_build_requires_managing_builds(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);

        $this->expectException(AuthorizationException::class);

        app(CreateBuild::class)($user, $plan, ['name' => '1.0.0']);
    }

    public function test_updating_a_build_requires_managing_builds(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);
        $build = Build::factory()->for($plan)->create();

        $this->expectException(AuthorizationException::class);

        app(UpdateBuild::class)($user, $build, ['name' => '1.0.1']);
    }

    public function test_deleting_a_build_requires_managing_builds(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);
        $build = Build::factory()->for($plan)->create();

        $this->expectException(AuthorizationException::class);

        app(DeleteBuild::class)($user, $build);
    }

    public function test_a_plan_role_can_withhold_managing_builds_the_project_role_grants(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCan($project, Ability::ManageBuilds);
        $this->assignPlanRole($user, $plan, Ability::PlanTestCases);

        $this->expectException(AuthorizationException::class);

        app(CreateBuild::class)($user, $plan, ['name' => '1.0.0']);
    }

    public function test_a_plan_role_governs_builds_on_that_plan_alone(): void
    {
        $project = TestProject::factory()->create();
        $granted = TestPlan::factory()->for($project)->create();
        $other = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($granted, Ability::ManageBuilds);

        $this->expectException(AuthorizationException::class);

        app(CreateBuild::class)($user, $other, ['name' => '1.0.0']);
    }

    public function test_linking_requires_planning_test_cases(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);

        $this->expectException(AuthorizationException::class);

        app(LinkTestPlanItem::class)($user, $plan, $this->versionIn($project));
    }

    public function test_unlinking_requires_planning_test_cases(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);

        $this->expectException(AuthorizationException::class);

        app(UnlinkTestPlanItem::class)($user, $item);
    }

    public function test_reordering_requires_planning_test_cases(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);

        $this->expectException(AuthorizationException::class);

        app(ReorderTestPlanItems::class)($user, $plan, [$item->id]);
    }

    public function test_setting_urgency_needs_its_own_ability(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);

        $this->expectException(AuthorizationException::class);

        app(SetTestPlanItemUrgency::class)($user, $item, TestCaseUrgency::High);
    }

    public function test_updating_a_linked_version_needs_its_own_ability(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $this->versionIn($project)->id,
        ]);

        $this->expectException(AuthorizationException::class);

        app(UpdateLinkedTestCaseVersion::class)($user, $item);
    }
}
