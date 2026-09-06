<?php

namespace Tests\Feature\Api;

use App\Enums\Ability;
use App\Models\TestPlan;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Planning\InteractsWithPlanningRoles;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use AuthenticatesApiTokens;
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_lists_only_projects_the_caller_can_view(): void
    {
        $visible = TestProject::factory()->restricted()->create(['name' => 'Alpha']);
        $hidden = TestProject::factory()->restricted()->create(['name' => 'Hidden']);
        $user = $this->userWhoCan($visible, Ability::ViewTestCases);

        $this->apiAs($user)
            ->getJson(route('api.v1.projects.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonMissing(['id' => $hidden->id]);
    }

    public function test_returns_403_when_the_caller_cannot_view_the_project(): void
    {
        $project = TestProject::factory()->restricted()->create();
        $user = $this->userWhoCan(TestProject::factory()->create(), Ability::ViewTestCases);

        $this->apiAs($user)
            ->getJson(route('api.v1.projects.show', $project))
            ->assertForbidden();
    }

    public function test_shows_a_project_the_caller_can_view(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->apiAs($user)
            ->getJson(route('api.v1.projects.show', $project))
            ->assertOk()
            ->assertJsonPath('data.prefix', $project->prefix);
    }

    public function test_lists_plans_the_caller_can_view_or_execute(): void
    {
        $project = TestProject::factory()->create();
        $readable = TestPlan::factory()->for($project)->create(['name' => 'Readable']);
        TestPlan::factory()->for($project)->create(['name' => 'Other']);
        $user = $this->userWhoCanOnPlan($readable, Ability::ExecuteTests);

        $this->apiAs($user)
            ->getJson(route('api.v1.projects.plans.index', $project))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $readable->id);
    }
}
