<?php

namespace Tests\Feature;

use App\Enums\TestCaseExecutionType;
use App\Enums\TestCaseImportance;
use App\Enums\TestCaseStatus;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestCaseVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_numbers_are_unique_within_a_test_case()
    {
        $case = TestCaseModel::factory()->create();
        TestCaseVersion::factory()->for($case, 'testCase')->version(2)->create();

        $this->expectException(QueryException::class);

        TestCaseVersion::factory()->for($case, 'testCase')->version(2)->create();
    }

    public function test_two_cases_may_each_have_a_version_with_the_same_number()
    {
        $first = TestCaseModel::factory()->create();
        $second = TestCaseModel::factory()->create();

        TestCaseVersion::factory()->for($first, 'testCase')->version(1)->create();
        $other = TestCaseVersion::factory()->for($second, 'testCase')->version(1)->create();

        $this->assertSame(1, $other->version);
    }

    public function test_a_version_starts_open_for_editing_and_in_draft()
    {
        $version = TestCaseVersion::factory()->create();

        $this->assertTrue($version->refresh()->is_open);
        $this->assertFalse($version->isFrozen());
        $this->assertSame(TestCaseStatus::Draft, $version->status);
    }

    public function test_a_frozen_version_reports_itself_as_frozen()
    {
        $version = TestCaseVersion::factory()->frozen()->create();

        $this->assertFalse($version->is_open);
        $this->assertTrue($version->isFrozen());
    }

    public function test_any_status_may_be_set_from_any_other()
    {
        $version = TestCaseVersion::factory()->create(['status' => TestCaseStatus::Final]);

        $version->update(['status' => TestCaseStatus::Draft]);

        $this->assertSame(TestCaseStatus::Draft, $version->refresh()->status);
    }

    public function test_status_importance_and_execution_type_are_cast_to_enums()
    {
        $version = TestCaseVersion::factory()->create([
            'status' => TestCaseStatus::Rework,
            'importance' => TestCaseImportance::High,
            'execution_type' => TestCaseExecutionType::Automated,
        ]);

        $version = $version->refresh();

        $this->assertSame(TestCaseStatus::Rework, $version->status);
        $this->assertSame(TestCaseImportance::High, $version->importance);
        $this->assertSame(TestCaseExecutionType::Automated, $version->execution_type);
        $this->assertDatabaseHas('test_case_versions', [
            'id' => $version->id,
            'status' => 'rework',
            'importance' => 'high',
            'execution_type' => 'automated',
        ]);
    }

    public function test_steps_are_listed_in_position_order()
    {
        $version = TestCaseVersion::factory()->create();
        $third = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(3)->create();
        $first = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(1)->create();
        $second = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(2)->create();

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $version->steps()->pluck('id')->all(),
        );
    }

    public function test_two_steps_may_share_a_position_so_that_reordering_can_renumber_them()
    {
        $version = TestCaseVersion::factory()->create();

        TestCaseStep::factory()->for($version, 'testCaseVersion')->at(1)->create();
        TestCaseStep::factory()->for($version, 'testCaseVersion')->at(1)->create();

        $this->assertCount(2, $version->steps);
    }

    public function test_deleting_a_version_removes_only_its_own_steps()
    {
        $case = TestCaseModel::factory()->create();
        $version = TestCaseVersion::factory()->for($case, 'testCase')->version(1)->create();
        TestCaseStep::factory()->for($version, 'testCaseVersion')->create();
        $survivor = TestCaseVersion::factory()->for($case, 'testCase')->version(2)->create();
        TestCaseStep::factory()->for($survivor, 'testCaseVersion')->create();

        $version->delete();

        $this->assertDatabaseCount('test_case_steps', 1);
        $this->assertDatabaseHas('test_case_steps', ['test_case_version_id' => $survivor->id]);
    }

    public function test_deleting_an_author_leaves_the_version_in_place_without_one()
    {
        $author = User::factory()->create();
        $version = TestCaseVersion::factory()->create([
            'author_id' => $author->id,
            'updater_id' => $author->id,
        ]);

        $author->delete();

        $version = $version->refresh();

        $this->assertNull($version->author_id);
        $this->assertNull($version->updater_id);
        $this->assertNull($version->author);
    }
}
