<?php

namespace Tests\Feature\TestSpecification;

use App\Enums\Ability;
use App\Models\Platform;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class VersionPlatformsTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_a_case_version_includes_its_platforms_and_the_design_vocabulary(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $case = TestCaseModel::factory()->withVersion()->create(['test_project_id' => $project->id]);
        $chrome = Platform::factory()->for($project)->named('Chrome')->create();
        Platform::factory()->for($project)->named('API')->executionOnly()->create();
        $case->latestVersion->platforms()->attach($chrome);

        $this->actingAs($user)
            ->get(route('specification.cases.show', [$project, $case]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected.case.version.platforms.0.name', 'Chrome')
                ->has('platforms', 1)
                ->where('platforms.0.name', 'Chrome')
                ->where('can.viewPlatforms', false)
            );
    }

    public function test_version_platforms_can_be_saved_from_the_specification(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $case = TestCaseModel::factory()->withVersion()->create(['test_project_id' => $project->id]);
        $chrome = Platform::factory()->for($project)->named('Chrome')->create();
        $ios = Platform::factory()->for($project)->named('iOS')->create();
        $version = $case->latestVersion;

        $this->actingAs($user)
            ->put(route('test-case-versions.platforms.update', $version), [
                'platforms' => [$chrome->id, $ios->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Chrome', 'iOS'], $version->platforms()->orderBy('name')->pluck('name')->all());
    }

    public function test_submitting_nothing_clears_every_version_platform(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $case = TestCaseModel::factory()->withVersion()->create(['test_project_id' => $project->id]);
        $version = $case->latestVersion;
        $version->platforms()->attach(Platform::factory()->for($project)->create());

        $this->actingAs($user)
            ->put(route('test-case-versions.platforms.update', $version))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $version->platforms()->count());
    }

    public function test_tagging_a_version_requires_manage_test_cases(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ViewPlatforms);
        $case = TestCaseModel::factory()->withVersion()->create(['test_project_id' => $project->id]);
        $platform = Platform::factory()->for($project)->create();

        $this->actingAs($user)
            ->put(route('test-case-versions.platforms.update', $case->latestVersion), [
                'platforms' => [$platform->id],
            ])
            ->assertForbidden();
    }
}
