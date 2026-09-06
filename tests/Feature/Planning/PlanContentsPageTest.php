<?php

namespace Tests\Feature\Planning;

use App\Enums\Ability;
use App\Enums\TestCaseUrgency;
use App\Models\Milestone;
use App\Models\Platform;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PlanContentsPageTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $plan = TestPlan::factory()->create();

        $this->get(route('plans.show', $plan))->assertRedirect(route('login'));
    }

    public function test_viewing_the_plan_list_is_not_enough_to_open_contents(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->actingAs($user)
            ->get(route('plans.show', $plan))
            ->assertForbidden();
    }

    public function test_a_plan_role_that_may_link_cases_can_open_contents(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create(['name' => 'Regression']);
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);

        $this->actingAs($user)
            ->get(route('plans.show', $plan))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('test-plans/show')
                ->where('plan.name', 'Regression')
                ->where('can.planTestCases', true)
                ->where('can.manageBuilds', false)
                ->where('currentProject.id', $project->id)
            );
    }

    public function test_the_contents_page_lists_platforms_builds_and_items(): void
    {
        $project = TestProject::factory()->create(['prefix' => 'QA']);
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCan($project, Ability::CreateTestPlans, Ability::PlanTestCases, Ability::ManageBuilds);
        $platform = Platform::factory()->for($project)->named('Chrome')->create();
        $plan->platforms()->attach($platform);
        $version = $this->versionIn($project);
        TestPlanItem::factory()->for($plan)->onPlatform($platform->id)->create([
            'test_case_version_id' => $version->id,
        ]);
        $plan->builds()->create(['name' => '1.0.0', 'is_open' => true, 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('plans.show', $plan))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('platforms.0.name', 'Chrome')
                ->where('platforms.0.assigned', true)
                ->where('builds.0.name', '1.0.0')
                ->where('items.0.full_external_id', $version->testCase->fullExternalId())
                ->where('items.0.platform.name', 'Chrome')
                ->has('linkable', 1)
            );
    }

    public function test_the_contents_page_lists_milestones(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCan($project, Ability::CreateTestPlans, Ability::ManageMilestones);
        Milestone::factory()->for($plan, 'testPlan')->create(['name' => 'Beta']);

        $this->actingAs($user)
            ->get(route('plans.show', $plan))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('milestones.0.name', 'Beta')
                ->where('can.manageMilestones', true)
            );
    }

    public function test_plan_platforms_can_be_saved_from_the_contents_page(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManagePlanPlatforms);
        $platform = Platform::factory()->for($project)->named('Chrome')->create();

        $this->actingAs($user)
            ->put(route('plans.platforms.update', $plan), ['platforms' => [$platform->id]])
            ->assertRedirect(route('plans.show', $plan))
            ->assertSessionHasNoErrors();

        $this->assertTrue($plan->platforms()->whereKey($platform)->exists());
    }

    public function test_a_version_can_be_linked_and_unlinked_through_http(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);
        $version = $this->versionIn($project);

        $this->actingAs($user)
            ->post(route('plan-items.store', $plan), [
                'test_case_version_id' => $version->id,
                'urgency' => 'high',
            ])
            ->assertRedirect(route('plans.show', $plan))
            ->assertSessionHasNoErrors();

        $item = TestPlanItem::query()->sole();

        $this->assertSame($version->id, $item->test_case_version_id);
        $this->assertSame(TestCaseUrgency::High, $item->urgency);

        $this->actingAs($user)
            ->delete(route('plan-items.destroy', $item))
            ->assertRedirect(route('plans.show', $plan))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, TestPlanItem::query()->count());
    }

    public function test_linking_without_a_version_is_rejected(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);

        $this->actingAs($user)
            ->post(route('plan-items.store', $plan), [])
            ->assertSessionHasErrors('test_case_version_id');
    }

    public function test_urgency_and_linked_version_can_be_updated_through_http(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan(
            $plan,
            Ability::SetTestCaseUrgency,
            Ability::UpdateLinkedTestCaseVersions,
        );
        $case = TestCaseModel::factory()->create(['test_project_id' => $project->id]);
        $first = TestCaseVersion::factory()->for($case, 'testCase')->version(1)->create();
        TestCaseVersion::factory()->for($case, 'testCase')->version(2)->create();
        $item = TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $first->id,
        ]);

        $this->actingAs($user)
            ->put(route('plan-items.urgency', $item), ['urgency' => 'low'])
            ->assertSessionHasNoErrors();

        $this->assertSame(TestCaseUrgency::Low, $item->fresh()->urgency);

        $this->actingAs($user)
            ->put(route('plan-items.version', $item))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $item->fresh()->testCaseVersion->version);
    }

    public function test_items_can_be_reordered_through_http(): void
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

        $this->actingAs($user)
            ->post(route('plan-items.reorder', $plan), ['order' => [$second->id, $first->id]])
            ->assertRedirect(route('plans.show', $plan))
            ->assertSessionHasNoErrors();

        $this->assertSame([$second->id, $first->id], $plan->items()->pluck('id')->all());
    }

    public function test_planning_writes_are_forbidden_without_the_matching_ability(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);
        $version = $this->versionIn($project);

        $this->actingAs($user)
            ->post(route('plan-items.store', $plan), ['test_case_version_id' => $version->id])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('plans.platforms.update', $plan), ['platforms' => []])
            ->assertForbidden();
    }
}
