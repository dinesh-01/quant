<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_platform_belongs_to_one_project()
    {
        $project = TestProject::factory()->create();
        $platform = Platform::factory()->for($project)->named('Chrome')->create();

        $this->assertTrue($platform->testProject->is($project));
        $this->assertTrue($project->platforms->contains($platform));
        $this->assertSame('Chrome', $project->platforms->first()->name);
    }

    public function test_platform_names_are_unique_within_a_project()
    {
        $project = TestProject::factory()->create();
        Platform::factory()->for($project)->named('Chrome')->create();

        $this->expectException(QueryException::class);

        Platform::factory()->for($project)->named('Chrome')->create();
    }

    public function test_platform_names_collide_across_case_inside_one_project()
    {
        $project = TestProject::factory()->create();
        Platform::factory()->for($project)->named('Chrome')->create();

        $this->expectException(QueryException::class);

        Platform::factory()->for($project)->named('chrome')->create();
    }

    public function test_two_projects_may_each_have_a_platform_of_the_same_name()
    {
        $first = Platform::factory()->named('Chrome')->create();
        $second = Platform::factory()->named('Chrome')->create();

        $this->assertFalse($first->testProject->is($second->testProject));
        $this->assertSame('Chrome', $second->name);
    }

    public function test_a_platform_can_be_assigned_to_a_plan_and_to_a_version()
    {
        $project = TestProject::factory()->create();
        $platform = Platform::factory()->for($project)->create();
        $plan = TestPlan::factory()->for($project)->create();
        $suite = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($suite, 'testSuite')->create();
        $version = TestCaseVersion::factory()->for($case, 'testCase')->create();

        $plan->platforms()->attach($platform);
        $version->platforms()->attach($platform);

        $this->assertTrue($plan->fresh()->platforms->contains($platform));
        $this->assertTrue($version->fresh()->platforms->contains($platform));
        $this->assertTrue($platform->fresh()->testPlans->contains($plan));
        $this->assertTrue($platform->fresh()->testCaseVersions->contains($version));
    }

    public function test_deleting_a_project_removes_its_platforms()
    {
        $project = TestProject::factory()->create();
        Platform::factory()->for($project)->create();
        $survivor = Platform::factory()->create();

        $project->delete();

        $this->assertDatabaseCount('platforms', 1);
        $this->assertDatabaseHas('platforms', ['id' => $survivor->id]);
    }

    public function test_deleting_a_platform_clears_its_plan_and_version_assignments()
    {
        $project = TestProject::factory()->create();
        $platform = Platform::factory()->for($project)->create();
        $plan = TestPlan::factory()->for($project)->create();
        $suite = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($suite, 'testSuite')->create();
        $version = TestCaseVersion::factory()->for($case, 'testCase')->create();

        $plan->platforms()->attach($platform);
        $version->platforms()->attach($platform);

        $platform->delete();

        $this->assertDatabaseEmpty('test_plan_platform');
        $this->assertDatabaseEmpty('platform_test_case_version');
        $this->assertTrue($plan->fresh()->exists);
        $this->assertTrue($version->fresh()->exists);
    }

    public function test_enablement_flags_and_open_state_default_to_true()
    {
        $platform = Platform::factory()->create();

        $this->assertTrue($platform->enable_on_design);
        $this->assertTrue($platform->enable_on_execution);
        $this->assertTrue($platform->is_open);
    }
}
