<?php

namespace Tests\Feature\Planning;

use App\Actions\TestSpecification\CopyTestCase;
use App\Actions\TestSpecification\CopyTestSuite;
use App\Enums\Ability;
use App\Models\Platform;
use App\Models\Role;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformCopyTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_copying_a_case_within_a_project_carries_its_version_platforms(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $target = TestSuite::factory()->for($project)->create();
        $case = $this->caseIn($project, $suite);
        $case->latestVersion->platforms()->attach(
            Platform::factory()->for($project)->named('Chrome')->create(),
        );

        $copy = app(CopyTestCase::class)($user, $case, $target);

        $this->assertSame(['Chrome'], $copy->latestVersion->platforms()->pluck('name')->all());
    }

    /**
     * A platform belongs to a project, so a copy may only point at the
     * target's own vocabulary. Matching is by name.
     */
    public function test_copying_across_projects_matches_the_targets_platform_by_name(): void
    {
        $source = TestProject::factory()->create();
        $target = TestProject::factory()->create();
        $user = $this->crossProjectUser($source, $target);

        $case = $this->caseIn($source, TestSuite::factory()->for($source)->create());
        $case->latestVersion->platforms()->attach(
            Platform::factory()->for($source)->named('Chrome')->create(),
        );

        $theirs = Platform::factory()->for($target)->named('Chrome')->create();
        $targetSuite = TestSuite::factory()->for($target)->create();

        $copy = app(CopyTestCase::class)($user, $case, $targetSuite);

        $this->assertSame([$theirs->id], $copy->latestVersion->platforms()->pluck('platforms.id')->all());
    }

    public function test_the_name_match_ignores_case(): void
    {
        $source = TestProject::factory()->create();
        $target = TestProject::factory()->create();
        $user = $this->crossProjectUser($source, $target);

        $case = $this->caseIn($source, TestSuite::factory()->for($source)->create());
        $case->latestVersion->platforms()->attach(
            Platform::factory()->for($source)->named('Chrome')->create(),
        );

        Platform::factory()->for($target)->named('chrome')->create();

        $copy = app(CopyTestCase::class)($user, $case, TestSuite::factory()->for($target)->create());

        $this->assertSame(['chrome'], $copy->latestVersion->platforms()->pluck('name')->all());
    }

    /**
     * Dropped rather than created: creating it would let anyone who may copy
     * a case write into a vocabulary that `manage_platforms` exists to control.
     */
    public function test_a_platform_the_target_project_does_not_have_is_dropped(): void
    {
        $source = TestProject::factory()->create();
        $target = TestProject::factory()->create();
        $user = $this->crossProjectUser($source, $target);

        $case = $this->caseIn($source, TestSuite::factory()->for($source)->create());
        $case->latestVersion->platforms()->attach([
            Platform::factory()->for($source)->named('shared')->create()->id,
            Platform::factory()->for($source)->named('unknown-here')->create()->id,
        ]);

        Platform::factory()->for($target)->named('shared')->create();

        $copy = app(CopyTestCase::class)($user, $case, TestSuite::factory()->for($target)->create());

        $this->assertSame(['shared'], $copy->latestVersion->platforms()->pluck('name')->all());
        $this->assertSame(2, Platform::query()->where('test_project_id', $source->id)->count());
        $this->assertSame(1, Platform::query()->where('test_project_id', $target->id)->count());
    }

    public function test_copying_a_suite_carries_the_platforms_of_its_versions(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $case = $this->caseIn($project, $suite);
        $case->latestVersion->platforms()->attach(
            Platform::factory()->for($project)->named('Chrome')->create(),
        );

        $copy = app(CopyTestSuite::class)($user, $suite);

        $this->assertSame(
            ['Chrome'],
            $copy->testCases()->sole()->latestVersion->platforms()->pluck('name')->all(),
        );
    }

    private function caseIn(TestProject $project, TestSuite $suite): TestCaseModel
    {
        return TestCaseModel::factory()
            ->for($suite, 'testSuite')
            ->withVersion()
            ->create(['test_project_id' => $project->id]);
    }

    private function crossProjectUser(TestProject $source, TestProject $target): User
    {
        $user = User::factory()->for(Role::factory()->granting())->create();

        $this->assignProjectRole($user, $source, Ability::ViewTestCases);
        $this->assignProjectRole($user, $target, Ability::ManageTestCases);

        return $user;
    }
}
