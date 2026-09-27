<?php

namespace Tests\Feature\CodeTrackers;

use App\Enums\Ability;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class CodeCoveragePageTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_view_code_trackers_opens_the_coverage_page(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewCodeTrackers);

        $this->actingAs($user)
            ->get(route('code-coverage.index', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('code-coverage/index')
                ->where('cases', 0)
                ->has('links', 0),
            );
    }

    public function test_view_test_cases_alone_is_forbidden(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->actingAs($user)
            ->get(route('code-coverage.index', $project))
            ->assertForbidden();
    }
}
