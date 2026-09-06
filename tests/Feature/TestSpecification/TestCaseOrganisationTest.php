<?php

namespace Tests\Feature\TestSpecification;

use App\Actions\TestSpecification\CopyTestCase;
use App\Actions\TestSpecification\MoveTestCase;
use App\Actions\TestSpecification\ReorderTestCases;
use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TestCaseOrganisationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_case_can_be_moved_to_another_suite_and_keeps_its_external_id()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $origin = TestSuite::factory()->for($project)->create();
        $target = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($origin, 'testSuite')->withVersion()->create();
        $externalId = $case->external_id;

        app(MoveTestCase::class)($user, $case, $target);

        $this->assertSame($target->id, $case->refresh()->test_suite_id);
        $this->assertSame($externalId, $case->external_id);
        $this->assertSame($project->id, $case->test_project_id);
    }

    public function test_a_moved_case_is_placed_after_the_cases_already_in_the_target_suite()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $origin = TestSuite::factory()->for($project)->create();
        $target = TestSuite::factory()->for($project)->create();
        TestCaseModel::factory()->for($target, 'testSuite')->create(['sort_order' => 4]);
        $case = TestCaseModel::factory()->for($origin, 'testSuite')->create();

        app(MoveTestCase::class)($user, $case, $target);

        $this->assertSame(5, $case->refresh()->sort_order);
    }

    public function test_a_case_cannot_be_moved_to_another_project()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $case = TestCaseModel::factory()->create(['test_project_id' => $project->id]);
        $foreign = TestSuite::factory()->create();

        $this->expectException(ValidationException::class);

        app(MoveTestCase::class)($user, $case, $foreign);
    }

    public function test_copying_a_case_duplicates_every_version_with_a_new_external_id()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $target = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($suite, 'testSuite')->create(['name' => 'Reset works']);
        $first = TestCaseVersion::factory()->for($case, 'testCase')->version(1)->create(['summary' => 'One']);
        TestCaseVersion::factory()->for($case, 'testCase')->version(2)->create(['summary' => 'Two']);
        TestCaseStep::factory()->for($first, 'testCaseVersion')->at(1)->create(['actions' => 'Click reset']);

        $copy = $this->copyAction()($user, $case->refresh(), $target);

        $this->assertNotSame($case->id, $copy->id);
        $this->assertSame('Reset works', $copy->name);
        $this->assertSame($target->id, $copy->test_suite_id);
        $this->assertSame(2, $copy->external_id);
        $this->assertSame(['One', 'Two'], $copy->versions->pluck('summary')->all());
        $this->assertSame('Click reset', $copy->versions->firstWhere('version', 1)->steps->sole()->actions);
    }

    public function test_a_case_can_be_copied_into_another_project()
    {
        $source = TestProject::factory()->create();
        $destination = TestProject::factory()->create();
        $user = User::factory()->for(Role::factory()->granting())->create();
        $this->assignProjectRole($user, $source, Ability::ViewTestCases);
        $this->assignProjectRole($user, $destination, Ability::ManageTestCases);
        $case = TestCaseModel::factory()->withVersion()->create(['test_project_id' => $source->id]);
        $target = TestSuite::factory()->for($destination)->create();

        $copy = $this->copyAction()($user, $case, $target);

        $this->assertSame($destination->id, $copy->test_project_id);
        $this->assertSame(1, $copy->external_id, 'numbered from the target project sequence');
        $this->assertSame(1, $destination->refresh()->test_case_counter);
    }

    public function test_cases_in_a_suite_are_renumbered_from_one()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $first = TestCaseModel::factory()->for($suite, 'testSuite')->create(['sort_order' => 6]);
        $second = TestCaseModel::factory()->for($suite, 'testSuite')->create(['sort_order' => 11]);

        (new ReorderTestCases)($user, $suite, [$second->id, $first->id]);

        $this->assertSame([$second->id, $first->id], $suite->testCases()->pluck('id')->all());
        $this->assertSame([1, 2], $suite->testCases()->pluck('sort_order')->all());
    }

    public function test_reordering_cases_requires_every_case_to_be_listed_exactly_once()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $first = TestCaseModel::factory()->for($suite, 'testSuite')->create();
        TestCaseModel::factory()->for($suite, 'testSuite')->create();

        $this->expectException(ValidationException::class);

        (new ReorderTestCases)($user, $suite, [$first->id, $first->id]);
    }

    private function copyAction(): CopyTestCase
    {
        return app(CopyTestCase::class);
    }

    private function userWhoCan(TestProject $project, Ability ...$abilities): User
    {
        $user = User::factory()->for(Role::factory()->granting())->create();

        $this->assignProjectRole($user, $project, ...$abilities);

        return $user;
    }

    private function assignProjectRole(User $user, TestProject $project, Ability ...$abilities): void
    {
        $user->projectRoles()->attach(
            Role::factory()->granting(...$abilities)->create(),
            ['test_project_id' => $project->id],
        );
    }
}
