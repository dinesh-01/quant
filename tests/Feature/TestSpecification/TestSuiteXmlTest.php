<?php

namespace Tests\Feature\TestSpecification;

use App\Enums\Ability;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestSuiteXmlTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_suite_xml_routes_are_gone(): void
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $user = $this->userWhoCan(
            $project,
            Ability::ViewTestCases,
            Ability::ManageTestCases,
        );

        $this->actingAs($user)
            ->get("/test-suites/{$suite->id}/export")
            ->assertNotFound();

        $this->actingAs($user)
            ->post("/projects/{$project->id}/specification/import")
            ->assertNotFound();
    }
}
