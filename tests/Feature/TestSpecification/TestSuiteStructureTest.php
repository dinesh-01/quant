<?php

namespace Tests\Feature\TestSpecification;

use App\Actions\TestSpecification\CopyTestSuite;
use App\Actions\TestSpecification\CreateTestSuite;
use App\Actions\TestSpecification\MoveTestSuite;
use App\Actions\TestSpecification\ReorderTestSuites;
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

class TestSuiteStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_suite_can_be_created_at_the_project_root()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);

        $suite = app(CreateTestSuite::class)($user, $project, null, ['name' => 'Authentication']);

        $this->assertNull($suite->parent_id);
        $this->assertSame($project->id, $suite->test_project_id);
        $this->assertSame(1, $suite->sort_order);
    }

    public function test_a_new_suite_is_placed_after_its_last_sibling()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        TestSuite::factory()->for($project)->create(['sort_order' => 5]);

        $suite = app(CreateTestSuite::class)($user, $project, null, ['name' => 'Later']);

        $this->assertSame(6, $suite->sort_order);
    }

    public function test_a_suite_can_be_nested_in_another()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $parent = TestSuite::factory()->for($project)->create();

        $suite = app(CreateTestSuite::class)($user, $project, $parent, ['name' => 'Nested']);

        $this->assertSame($parent->id, $suite->parent_id);
        $this->assertSame(2, $suite->depth());
    }

    public function test_a_parent_from_another_project_is_rejected()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $foreign = TestSuite::factory()->create();

        $this->expectException(ValidationException::class);

        app(CreateTestSuite::class)($user, $project, $foreign, ['name' => 'Nope']);
    }

    public function test_suites_cannot_be_created_beyond_the_nesting_limit()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $deepest = $this->chainOfSuites($project, TestSuite::MAX_DEPTH);

        $this->assertSame(TestSuite::MAX_DEPTH, $deepest->depth());

        $this->expectException(ValidationException::class);

        app(CreateTestSuite::class)($user, $project, $deepest, ['name' => 'One too deep']);
    }

    public function test_a_suite_can_be_moved_under_another_parent()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $child = TestSuite::factory()->childOf($suite)->create();
        $newParent = TestSuite::factory()->for($project)->create();

        app(MoveTestSuite::class)($user, $suite, $newParent);

        $this->assertSame($newParent->id, $suite->refresh()->parent_id);
        $this->assertSame($suite->id, $child->refresh()->parent_id, 'the subtree travels with it');
        $this->assertSame([$newParent->id, $suite->id, $child->id], $child->path()->pluck('id')->all());
    }

    public function test_a_suite_can_be_moved_to_the_project_root()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $parent = TestSuite::factory()->for($project)->create();
        $suite = TestSuite::factory()->childOf($parent)->create();

        app(MoveTestSuite::class)($user, $suite, null);

        $this->assertNull($suite->refresh()->parent_id);
    }

    public function test_a_suite_cannot_be_moved_into_its_own_subtree()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $descendant = TestSuite::factory()->childOf(TestSuite::factory()->childOf($suite)->create())->create();

        $this->expectException(ValidationException::class);

        app(MoveTestSuite::class)($user, $suite, $descendant);
    }

    public function test_a_suite_cannot_be_moved_into_itself()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        $this->expectException(ValidationException::class);

        app(MoveTestSuite::class)($user, $suite, $suite);
    }

    public function test_a_suite_cannot_be_moved_to_another_project()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $foreign = TestSuite::factory()->create();

        $this->expectException(ValidationException::class);

        app(MoveTestSuite::class)($user, $suite, $foreign);
    }

    public function test_a_move_that_would_breach_the_nesting_limit_is_rejected()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $deepest = $this->chainOfSuites($project, TestSuite::MAX_DEPTH - 1);
        $branch = TestSuite::factory()->for($project)->create();
        TestSuite::factory()->childOf(TestSuite::factory()->childOf($branch)->create())->create();

        $this->assertSame(2, $branch->descendantDepth());

        $this->expectException(ValidationException::class);

        app(MoveTestSuite::class)($user, $branch, $deepest);
    }

    public function test_sibling_suites_are_renumbered_from_one()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $first = TestSuite::factory()->for($project)->create(['sort_order' => 3]);
        $second = TestSuite::factory()->for($project)->create(['sort_order' => 8]);

        (new ReorderTestSuites)($user, $project, null, [$second->id, $first->id]);

        $this->assertSame([$second->id, $first->id], $project->rootTestSuites()->pluck('id')->all());
        $this->assertSame(1, $second->refresh()->sort_order);
        $this->assertSame(2, $first->refresh()->sort_order);
    }

    public function test_reordering_requires_every_sibling_to_be_listed_exactly_once()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $first = TestSuite::factory()->for($project)->create();
        TestSuite::factory()->for($project)->create();

        $this->expectException(ValidationException::class);

        (new ReorderTestSuites)($user, $project, null, [$first->id]);
    }

    public function test_copying_a_suite_duplicates_its_subtree_cases_versions_and_steps()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $source = TestSuite::factory()->for($project)->create(['name' => 'Authentication']);
        $child = TestSuite::factory()->childOf($source)->create(['name' => 'Passwords']);
        $case = TestCaseModel::factory()->for($child, 'testSuite')->create(['name' => 'Reset works']);
        $version = TestCaseVersion::factory()->for($case, 'testCase')->version(1)->create();
        TestCaseStep::factory()->for($version, 'testCaseVersion')->at(1)->create(['actions' => 'Click reset']);
        $target = TestSuite::factory()->for($project)->create();

        $copy = $this->copyAction()($user, $source, $target);

        $this->assertSame($target->id, $copy->parent_id);
        $this->assertSame('Authentication', $copy->name);

        $copiedChild = $copy->children->sole();
        $this->assertSame('Passwords', $copiedChild->name);

        $copiedCase = $copiedChild->testCases->sole();
        $this->assertSame('Reset works', $copiedCase->name);
        $this->assertNotSame($case->id, $copiedCase->id);

        $copiedVersion = $copiedCase->versions->sole();
        $this->assertSame(1, $copiedVersion->version);
        $this->assertSame('Click reset', $copiedVersion->steps->sole()->actions);
    }

    public function test_copied_cases_get_fresh_external_ids_and_the_originals_keep_theirs()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $source = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($source, 'testSuite')->withVersion()->create();
        $target = TestSuite::factory()->for($project)->create();

        $copy = $this->copyAction()($user, $source, $target);

        $this->assertSame(1, $case->refresh()->external_id);
        $this->assertSame(2, $copy->testCases->sole()->external_id);
        $this->assertSame(2, $project->refresh()->test_case_counter);
    }

    public function test_copying_preserves_the_freeze_state_of_every_version()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $source = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($source, 'testSuite')->create();
        TestCaseVersion::factory()->for($case, 'testCase')->version(1)->frozen()->create();
        TestCaseVersion::factory()->for($case, 'testCase')->version(2)->create();
        $target = TestSuite::factory()->for($project)->create();

        $copy = $this->copyAction()($user, $source, $target);

        $versions = $copy->testCases->sole()->versions;

        $this->assertSame([1, 2], $versions->pluck('version')->all());
        $this->assertFalse($versions->firstWhere('version', 1)->is_open);
        $this->assertTrue($versions->firstWhere('version', 2)->is_open);
    }

    public function test_a_suite_can_be_copied_into_another_project()
    {
        $source = TestProject::factory()->create();
        $destination = TestProject::factory()->create();
        $user = User::factory()->for(Role::factory()->granting())->create();
        $this->assignProjectRole($user, $source, Ability::ViewTestCases);
        $this->assignProjectRole($user, $destination, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($source)->create();
        TestCaseModel::factory()->for($suite, 'testSuite')->withVersion()->create();

        $copy = $this->copyAction()($user, $suite, null, $destination);

        $this->assertSame($destination->id, $copy->test_project_id);
        $this->assertNull($copy->parent_id);
        $this->assertSame($destination->id, $copy->testCases->sole()->test_project_id);
        $this->assertSame(1, $copy->testCases->sole()->external_id);
        $this->assertSame(1, $destination->refresh()->test_case_counter);
    }

    public function test_a_suite_cannot_be_copied_into_its_own_subtree()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $child = TestSuite::factory()->childOf($suite)->create();

        $this->expectException(ValidationException::class);

        $this->copyAction()($user, $suite, $child);
    }

    private function copyAction(): CopyTestSuite
    {
        return app(CopyTestSuite::class);
    }

    /**
     * Build a straight line of nested suites and return the deepest.
     */
    private function chainOfSuites(TestProject $project, int $levels): TestSuite
    {
        $suite = TestSuite::factory()->for($project)->create();

        for ($level = 1; $level < $levels; $level++) {
            $suite = TestSuite::factory()->childOf($suite)->create();
        }

        return $suite;
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
