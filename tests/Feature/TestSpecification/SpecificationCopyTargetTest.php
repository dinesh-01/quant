<?php

namespace Tests\Feature\TestSpecification;

use App\Enums\Ability;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecificationCopyTargetTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_copy_targets_list_other_projects_the_user_can_write(): void
    {
        $here = TestProject::factory()->create(['name' => 'Here']);
        $there = TestProject::factory()->create(['name' => 'There']);
        $hidden = TestProject::factory()->create(['name' => 'Hidden']);
        $user = $this->userWhoCan($here, Ability::ManageTestCases, Ability::ViewTestCases);
        $this->assignProjectRole($user, $there, Ability::ManageTestCases, Ability::ViewTestCases);
        $this->assignProjectRole($user, $hidden, Ability::ViewTestCases);

        $this->actingAs($user)
            ->getJson(route('specification.copy-targets', $here))
            ->assertOk()
            ->assertJsonPath('projects.0.id', $there->id)
            ->assertJsonMissing(['id' => $hidden->id])
            ->assertJsonMissing(['id' => $here->id]);
    }

    public function test_copy_targets_require_manage(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->actingAs($user)
            ->getJson(route('specification.copy-targets', $project))
            ->assertForbidden();
    }
}
