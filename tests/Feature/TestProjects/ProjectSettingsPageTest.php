<?php

namespace Tests\Feature\TestProjects;

use App\Enums\Ability;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class ProjectSettingsPageTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_a_project_member_can_open_settings(): void
    {
        $project = TestProject::factory()->create(['name' => 'Checkout']);
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->actingAs($user)
            ->get(route('project-settings.index', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('project-settings/show')
                ->where('project.name', 'Checkout')
                ->where('can.manageProject', false),
            );
    }

    public function test_an_unrelated_user_is_forbidden(): void
    {
        $project = TestProject::factory()->restricted()->create();
        $user = $this->userWhoCan(TestProject::factory()->create(), Ability::ViewTestCases);

        $this->actingAs($user)
            ->get(route('project-settings.index', $project))
            ->assertForbidden();
    }
}
