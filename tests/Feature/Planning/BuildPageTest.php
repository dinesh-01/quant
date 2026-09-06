<?php

namespace Tests\Feature\Planning;

use App\Enums\Ability;
use App\Models\Build;
use App\Models\TestPlan;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BuildPageTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_a_build_can_be_created_from_the_plan(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);

        $this->actingAs($user)
            ->get(route('builds.create', $plan))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('builds/create')
                ->where('plan.id', $plan->id)
                ->where('currentProject.id', $project->id)
            );

        $this->actingAs($user)
            ->post(route('builds.store', $plan), [
                'name' => '1.4.2',
                'notes' => 'RC',
                'is_open' => '1',
                'is_active' => '1',
                'release_date' => '2026-09-01',
            ])
            ->assertRedirect(route('plans.show', $plan))
            ->assertSessionHasNoErrors();

        $build = Build::query()->sole();

        $this->assertSame('1.4.2', $build->name);
        $this->assertSame('RC', $build->notes);
        $this->assertTrue($build->is_open);
        $this->assertTrue($build->is_active);
        $this->assertSame('2026-09-01', $build->release_date?->toDateString());
    }

    public function test_two_builds_on_one_plan_cannot_share_a_name(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);
        Build::factory()->for($plan)->create(['name' => '1.4.2']);

        $this->actingAs($user)
            ->post(route('builds.store', $plan), ['name' => '1.4.2'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Build::query()->count());
    }

    public function test_a_build_can_be_updated_and_deleted(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);
        $build = Build::factory()->for($plan)->create(['name' => '1.4.2']);

        $this->actingAs($user)
            ->get(route('builds.edit', $build))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('builds/edit')
                ->where('build.name', '1.4.2')
                ->where('currentProject.id', $project->id)
            );

        $this->actingAs($user)
            ->put(route('builds.update', $build), [
                'name' => '1.4.3',
                'is_open' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('plans.show', $plan))
            ->assertSessionHasNoErrors();

        $this->assertSame('1.4.3', $build->fresh()->name);

        $this->actingAs($user)
            ->delete(route('builds.destroy', $build))
            ->assertRedirect(route('plans.show', $plan))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Build::query()->count());
    }

    public function test_managing_builds_requires_the_ability_on_the_plan(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::PlanTestCases);

        $this->actingAs($user)
            ->get(route('builds.create', $plan))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('builds.store', $plan), ['name' => '1.0'])
            ->assertForbidden();
    }

    public function test_managing_one_plan_does_not_reach_another(): void
    {
        $project = TestProject::factory()->create();
        $ours = TestPlan::factory()->for($project)->create();
        $theirs = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($ours, Ability::ManageBuilds);
        $build = Build::factory()->for($theirs)->create();

        $this->actingAs($user)
            ->post(route('builds.store', $theirs), ['name' => '1.0'])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('builds.destroy', $build))
            ->assertForbidden();
    }
}
