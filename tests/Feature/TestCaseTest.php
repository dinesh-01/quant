<?php

namespace Tests\Feature;

use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_project_has_handed_out_no_external_ids()
    {
        $project = TestProject::factory()->create();

        $this->assertSame(0, $project->refresh()->test_case_counter);
    }

    public function test_the_external_id_counter_is_not_mass_assignable()
    {
        $project = TestProject::factory()->create()->refresh();

        $project->fill(['test_case_counter' => 99]);

        $this->assertSame(0, $project->test_case_counter);
    }

    public function test_external_ids_are_unique_within_a_project()
    {
        $suite = TestSuite::factory()->create();
        TestCaseModel::factory()->for($suite, 'testSuite')->create(['external_id' => 7]);

        $this->expectException(QueryException::class);

        TestCaseModel::factory()->for($suite, 'testSuite')->create(['external_id' => 7]);
    }

    public function test_the_same_external_id_may_be_used_in_a_different_project()
    {
        $first = TestSuite::factory()->create();
        $second = TestSuite::factory()->create();

        TestCaseModel::factory()->for($first, 'testSuite')->create(['external_id' => 7]);
        $other = TestCaseModel::factory()->for($second, 'testSuite')->create(['external_id' => 7]);

        $this->assertSame(7, $other->external_id);
        $this->assertNotSame($first->test_project_id, $second->test_project_id);
    }

    public function test_a_case_takes_its_project_from_the_suite_it_belongs_to()
    {
        $project = TestProject::factory()->create();
        $root = TestSuite::factory()->for($project)->create();
        $nested = TestSuite::factory()->childOf($root)->create();

        $case = TestCaseModel::factory()->for($nested, 'testSuite')->create();

        $this->assertSame($project->id, $case->test_project_id);
        $this->assertTrue($case->testProject->is($project));
        $this->assertTrue($case->testSuite->is($nested));
    }

    public function test_the_full_external_id_combines_the_project_prefix_and_the_number()
    {
        $project = TestProject::factory()->create(['prefix' => 'QUANTA']);
        $suite = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($suite, 'testSuite')->create(['external_id' => 42]);

        $this->assertSame('QUANTA-42', $case->fullExternalId());
    }

    public function test_the_latest_version_is_the_highest_numbered_one_not_the_newest_row()
    {
        $case = TestCaseModel::factory()->create();
        $highest = TestCaseVersion::factory()->for($case, 'testCase')->version(3)->create();
        TestCaseVersion::factory()->for($case, 'testCase')->version(2)->create();

        $this->assertTrue($case->refresh()->latestVersion->is($highest));
    }

    public function test_a_case_with_no_versions_has_no_latest_version()
    {
        $case = TestCaseModel::factory()->create();

        $this->assertNull($case->latestVersion);
    }

    public function test_versions_are_listed_in_version_order()
    {
        $case = TestCaseModel::factory()->create();
        $third = TestCaseVersion::factory()->for($case, 'testCase')->version(3)->create();
        $first = TestCaseVersion::factory()->for($case, 'testCase')->version(1)->create();
        $second = TestCaseVersion::factory()->for($case, 'testCase')->version(2)->create();

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $case->versions()->pluck('id')->all(),
        );
    }

    public function test_deleting_a_case_removes_its_versions_and_their_steps()
    {
        $case = TestCaseModel::factory()->create();
        $version = TestCaseVersion::factory()->for($case, 'testCase')->create();
        TestCaseStep::factory()->for($version, 'testCaseVersion')->create();
        $survivor = TestCaseModel::factory()->withVersion()->create();

        $case->delete();

        $this->assertDatabaseCount('test_cases', 1);
        $this->assertDatabaseHas('test_cases', ['id' => $survivor->id]);
        $this->assertDatabaseCount('test_case_versions', 1);
        $this->assertDatabaseEmpty('test_case_steps');
    }
}
