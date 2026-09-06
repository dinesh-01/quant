<?php

namespace Tests\Feature\Requirements;

use App\Enums\Ability;
use App\Models\Requirement;
use App\Models\RequirementSpec;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class RequirementPageTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_the_page_lists_the_spec_tree(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewRequirements);
        $root = RequirementSpec::factory()->for($project)->create([
            'name' => 'Login',
            'doc_id' => 'SPEC-LOGIN',
        ]);
        Requirement::factory()->for($root, 'requirementSpec')->create([
            'name' => 'Valid credentials',
            'doc_id' => 'REQ-LOGIN-1',
        ]);

        $this->actingAs($user)
            ->get(route('requirements.show', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('requirements/index')
                ->where('tree.0.name', 'Login')
                ->where('tree.0.doc_id', 'SPEC-LOGIN')
                ->where('tree.0.requirements.0.doc_id', 'REQ-LOGIN-1')
                ->where('currentProject.can.viewRequirements', true)
                ->where('selected', null)
            );
    }

    public function test_viewing_the_page_requires_the_view_ability(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project);

        $this->actingAs($user)
            ->get(route('requirements.show', $project))
            ->assertForbidden();
    }

    public function test_a_spec_and_requirement_can_be_created_through_http(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan(
            $project,
            Ability::ViewRequirements,
            Ability::ManageRequirements,
        );

        $this->actingAs($user)
            ->post(route('requirement-specs.store', $project), [
                'name' => 'Login',
                'doc_id' => 'SPEC-LOGIN',
            ])
            ->assertSessionHasNoErrors();

        $spec = RequirementSpec::query()->sole();

        $this->actingAs($user)
            ->post(route('requirements.store', $spec), [
                'name' => 'Valid credentials',
                'doc_id' => 'REQ-LOGIN-1',
                'status' => 'draft',
                'type' => 'feature',
                'expected_coverage' => 1,
            ])
            ->assertSessionHasNoErrors();

        $requirement = Requirement::query()->sole();

        $this->actingAs($user)
            ->get(route('requirements.items.show', [$project, $requirement]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected.type', 'requirement')
                ->where('selected.requirement.doc_id', 'REQ-LOGIN-1')
                ->where('selected.requirement.version.version', 1)
            );
    }
}
