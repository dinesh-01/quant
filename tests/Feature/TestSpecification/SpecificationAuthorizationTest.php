<?php

namespace Tests\Feature\TestSpecification;

use App\Actions\TestSpecification\AllocateExternalId;
use App\Actions\TestSpecification\CopyTestCase;
use App\Actions\TestSpecification\CopyTestSuite;
use App\Actions\TestSpecification\CreateTestCase;
use App\Actions\TestSpecification\CreateTestCaseVersion;
use App\Actions\TestSpecification\CreateTestSuite;
use App\Actions\TestSpecification\FreezeTestCaseVersion;
use App\Actions\TestSpecification\MoveTestCase;
use App\Actions\TestSpecification\MoveTestSuite;
use App\Actions\TestSpecification\ReorderTestCases;
use App\Actions\TestSpecification\ReorderTestCaseSteps;
use App\Actions\TestSpecification\ReorderTestSuites;
use App\Actions\TestSpecification\UnfreezeTestCaseVersion;
use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every write in the specification area authorizes inside the action rather
 * than in a controller, so the check cannot be skipped by a caller that forgets
 * it. These tests cover the denied path for each of them.
 *
 * AllocateExternalId is absent on purpose: it is an internal collaborator that
 * no request reaches, and the action that creates or copies the case
 * authorizes.
 */
class SpecificationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_suite_requires_managing_test_cases()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->expectException(AuthorizationException::class);

        app(CreateTestSuite::class)($user, $project, null, ['name' => 'Nope']);
    }

    public function test_moving_a_suite_requires_managing_test_cases()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $target = TestSuite::factory()->for($project)->create();

        $this->expectException(AuthorizationException::class);

        app(MoveTestSuite::class)($user, $suite, $target);
    }

    public function test_reordering_suites_requires_managing_test_cases()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        $this->expectException(AuthorizationException::class);

        (new ReorderTestSuites)($user, $project, null, [$suite->id]);
    }

    public function test_copying_a_suite_requires_managing_test_cases_in_the_target_project()
    {
        $source = TestProject::factory()->create();
        $destination = TestProject::factory()->create();
        $user = User::factory()->for(Role::factory()->granting())->create();
        $this->assignProjectRole($user, $source, Ability::ViewTestCases, Ability::ManageTestCases);
        $this->assignProjectRole($user, $destination, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($source)->create();

        $this->expectException(AuthorizationException::class);

        $this->copySuite()($user, $suite, null, $destination);
    }

    public function test_copying_a_suite_requires_viewing_test_cases_in_the_source_project()
    {
        $source = TestProject::factory()->create();
        $destination = TestProject::factory()->create();
        $user = User::factory()->for(Role::factory()->granting())->create();
        $this->assignProjectRole($user, $source, Ability::ManageTestCases);
        $this->assignProjectRole($user, $destination, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($source)->create();

        $this->expectException(AuthorizationException::class);

        $this->copySuite()($user, $suite, null, $destination);
    }

    public function test_creating_a_case_requires_managing_test_cases()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        $this->expectException(AuthorizationException::class);

        app(CreateTestCase::class)($user, $suite, ['name' => 'Nope']);
    }

    public function test_creating_a_version_requires_managing_test_cases()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $case = TestCaseModel::factory()->withVersion()->create(['test_project_id' => $project->id]);

        $this->expectException(AuthorizationException::class);

        app(CreateTestCaseVersion::class)($user, $case);
    }

    public function test_moving_a_case_requires_managing_test_cases()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $case = TestCaseModel::factory()->create(['test_project_id' => $project->id]);
        $target = TestSuite::factory()->for($project)->create();

        $this->expectException(AuthorizationException::class);

        app(MoveTestCase::class)($user, $case, $target);
    }

    public function test_copying_a_case_requires_managing_test_cases_in_the_target_project()
    {
        $source = TestProject::factory()->create();
        $destination = TestProject::factory()->create();
        $user = User::factory()->for(Role::factory()->granting())->create();
        $this->assignProjectRole($user, $source, Ability::ViewTestCases, Ability::ManageTestCases);
        $this->assignProjectRole($user, $destination, Ability::ViewTestCases);
        $case = TestCaseModel::factory()->withVersion()->create(['test_project_id' => $source->id]);
        $target = TestSuite::factory()->for($destination)->create();

        $this->expectException(AuthorizationException::class);

        $this->copyCase()($user, $case, $target);
    }

    public function test_reordering_cases_requires_managing_test_cases()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($suite, 'testSuite')->create();

        $this->expectException(AuthorizationException::class);

        (new ReorderTestCases)($user, $suite, [$case->id]);
    }

    public function test_reordering_steps_requires_managing_test_cases()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $case = TestCaseModel::factory()->create(['test_project_id' => $project->id]);
        $version = TestCaseVersion::factory()->for($case, 'testCase')->version(1)->create();
        $step = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(1)->create();

        $this->expectException(AuthorizationException::class);

        (new ReorderTestCaseSteps)($user, $version, [$step->id]);
    }

    public function test_freezing_needs_its_own_ability_and_managing_test_cases_is_not_enough()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $case = TestCaseModel::factory()->withVersion()->create(['test_project_id' => $project->id]);

        $this->expectException(AuthorizationException::class);

        app(FreezeTestCaseVersion::class)($user, $case->latestVersion);
    }

    public function test_unfreezing_needs_its_own_ability_and_managing_test_cases_is_not_enough()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $case = TestCaseModel::factory()->create(['test_project_id' => $project->id]);
        $version = TestCaseVersion::factory()->for($case, 'testCase')->version(1)->frozen()->create();

        $this->expectException(AuthorizationException::class);

        app(UnfreezeTestCaseVersion::class)($user, $version);
    }

    private function copySuite(): CopyTestSuite
    {
        return app(CopyTestSuite::class);
    }

    private function copyCase(): CopyTestCase
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
