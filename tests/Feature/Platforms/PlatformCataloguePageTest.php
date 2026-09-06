<?php

namespace Tests\Feature\Platforms;

use App\Enums\Ability;
use App\Models\Platform;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class PlatformCataloguePageTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $project = TestProject::factory()->create();

        $this->get(route('platforms.index', $project))->assertRedirect(route('login'));
    }

    public function test_reading_the_list_requires_the_view_ability(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);

        $this->actingAs($user)
            ->get(route('platforms.index', $project))
            ->assertForbidden();
    }

    public function test_the_list_only_shows_this_projects_platforms(): void
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::ViewPlatforms);
        Platform::factory()->for($ours)->named('Chrome')->create();
        Platform::factory()->for($theirs)->named('Safari')->create();

        $this->actingAs($user)
            ->get(route('platforms.index', $ours))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('platforms/index')
                ->has('platforms', 1)
                ->where('platforms.0.name', 'Chrome')
                ->where('can.manage', false)
            );
    }

    public function test_the_shared_project_prop_reports_the_platform_ability(): void
    {
        $project = TestProject::factory()->create();

        $this->actingAs($this->userWhoCan($project, Ability::ViewPlatforms))
            ->get(route('platforms.index', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currentProject.can.viewPlatforms', true)
                ->where('currentProject.can.viewSpecification', false)
                ->where('currentProject.id', $project->id)
            );
    }

    public function test_a_platform_can_be_added_through_the_catalogue(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlatforms);

        $this->actingAs($user)
            ->post(route('platforms.store', $project), [
                'name' => 'Chrome',
                'notes' => 'Desktop browser',
                'enable_on_design' => '1',
                'enable_on_execution' => '1',
                'is_open' => '1',
            ])
            ->assertRedirect(route('platforms.index', $project))
            ->assertSessionHasNoErrors();

        $platform = Platform::query()->sole();

        $this->assertSame('Chrome', $platform->name);
        $this->assertSame('Desktop browser', $platform->notes);
        $this->assertTrue($platform->enable_on_design);
        $this->assertTrue($platform->enable_on_execution);
        $this->assertTrue($platform->is_open);
    }

    public function test_two_platforms_in_one_project_cannot_share_a_name(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlatforms);
        Platform::factory()->for($project)->named('Chrome')->create();

        $this->actingAs($user)
            ->post(route('platforms.store', $project), ['name' => 'Chrome'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Platform::query()->count());
    }

    public function test_the_view_ability_alone_cannot_write(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewPlatforms);
        $platform = Platform::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('platforms.store', $project), ['name' => 'Chrome'])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('platforms.update', $platform), ['name' => 'Renamed'])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('platforms.destroy', $platform))
            ->assertForbidden();

        $this->assertSame(1, Platform::query()->count());
    }

    public function test_managing_one_project_does_not_reach_another(): void
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::ManagePlatforms);
        $platform = Platform::factory()->for($theirs)->create();

        $this->actingAs($user)
            ->post(route('platforms.store', $theirs), ['name' => 'Chrome'])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('platforms.destroy', $platform))
            ->assertForbidden();
    }

    public function test_a_platform_still_used_on_a_plan_cannot_be_deleted(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManagePlatforms);
        $platform = Platform::factory()->for($project)->named('Chrome')->create();
        $plan = TestPlan::factory()->for($project)->create();
        $plan->platforms()->attach($platform);
        TestPlanItem::factory()->for($plan)->onPlatform($platform->id)->create();

        $this->actingAs($user)
            ->get(route('platforms.edit', $platform))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('platforms/edit')
                ->where('platform.plan_items_count', 1)
            );

        $this->actingAs($user)
            ->delete(route('platforms.destroy', $platform))
            ->assertSessionHasErrors('platform');

        $this->assertModelExists($platform);
    }

    public function test_editing_a_platform_walks_current_project_from_the_platform(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewPlatforms, Ability::ManagePlatforms);
        $platform = Platform::factory()->for($project)->create();

        $this->actingAs($user)
            ->get(route('platforms.edit', $platform))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currentProject.id', $project->id)
                ->where('currentProject.can.viewPlatforms', true)
            );
    }
}
