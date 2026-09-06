<?php

namespace Tests\Feature\Requirements;

use App\Enums\Ability;
use App\Models\RequirementSpec;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class RequirementXmlTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_requirement_xml_routes_are_gone(): void
    {
        $project = TestProject::factory()->create();
        $spec = RequirementSpec::factory()->for($project)->create();
        $user = $this->userWhoCan(
            $project,
            Ability::ViewRequirements,
            Ability::ManageRequirements,
        );

        $this->actingAs($user)
            ->get("/requirement-specs/{$spec->id}/export")
            ->assertNotFound();

        $this->actingAs($user)
            ->post("/projects/{$project->id}/requirements/import")
            ->assertNotFound();
    }
}
