<?php

namespace Tests\Feature\Reports;

use App\Enums\Ability;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class CustomReportsPageTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_view_project_metrics_opens_the_custom_reports_shell(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewProjectMetrics);

        $this->actingAs($user)
            ->get(route('custom-reports.index', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('reports/custom')
                ->where('project.id', $project->id),
            );
    }

    public function test_view_test_cases_alone_is_forbidden(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->actingAs($user)
            ->get(route('custom-reports.index', $project))
            ->assertForbidden();
    }
}
