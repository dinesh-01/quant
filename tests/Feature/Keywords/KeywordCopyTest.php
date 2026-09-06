<?php

namespace Tests\Feature\Keywords;

use App\Actions\TestSpecification\CopyTestCase;
use App\Actions\TestSpecification\CopyTestSuite;
use App\Actions\TestSpecification\CreateTestCaseVersion;
use App\Enums\Ability;
use App\Models\Keyword;
use App\Models\Role;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class KeywordCopyTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_copying_a_case_within_a_project_carries_its_keywords()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $target = TestSuite::factory()->for($project)->create();
        $case = $this->caseIn($project, $suite);
        $case->keywords()->attach(Keyword::factory()->for($project)->named('smoke')->create());

        $copy = app(CopyTestCase::class)($user, $case, $target);

        $this->assertSame(['smoke'], $copy->keywords()->pluck('name')->all());
    }

    /**
     * A keyword belongs to a project, so a copy may only point at the target's
     * own vocabulary. Matching is by name.
     *
     * Legacy meant to map ids and shipped a bug — `copyKeywordsTo()` reads an
     * undefined `$mappings` rather than the argument it was given — so a
     * cross-project copy there keeps the source project's keyword ids.
     */
    public function test_copying_across_projects_matches_the_targets_keyword_by_name()
    {
        $source = TestProject::factory()->create();
        $target = TestProject::factory()->create();
        $user = $this->crossProjectUser($source, $target);

        $case = $this->caseIn($source, TestSuite::factory()->for($source)->create());
        $case->keywords()->attach(Keyword::factory()->for($source)->named('smoke')->create());

        $theirs = Keyword::factory()->for($target)->named('smoke')->create();
        $targetSuite = TestSuite::factory()->for($target)->create();

        $copy = app(CopyTestCase::class)($user, $case, $targetSuite);

        $this->assertSame([$theirs->id], $copy->keywords()->pluck('keywords.id')->all());
    }

    /**
     * Matched without regard to case, because the column's collation is, and
     * because `Smoke` in one project is what a person means by `smoke` in
     * another.
     */
    public function test_the_name_match_ignores_case()
    {
        $source = TestProject::factory()->create();
        $target = TestProject::factory()->create();
        $user = $this->crossProjectUser($source, $target);

        $case = $this->caseIn($source, TestSuite::factory()->for($source)->create());
        $case->keywords()->attach(Keyword::factory()->for($source)->named('Smoke')->create());

        Keyword::factory()->for($target)->named('smoke')->create();

        $copy = app(CopyTestCase::class)($user, $case, TestSuite::factory()->for($target)->create());

        $this->assertSame(['smoke'], $copy->keywords()->pluck('name')->all());
    }

    /**
     * Dropped rather than created: creating it would let anyone who may copy a
     * case write into a vocabulary that `manage_keywords` exists to control.
     */
    public function test_a_keyword_the_target_project_does_not_have_is_dropped()
    {
        $source = TestProject::factory()->create();
        $target = TestProject::factory()->create();
        $user = $this->crossProjectUser($source, $target);

        $case = $this->caseIn($source, TestSuite::factory()->for($source)->create());
        $case->keywords()->attach([
            Keyword::factory()->for($source)->named('shared')->create()->id,
            Keyword::factory()->for($source)->named('unknown-here')->create()->id,
        ]);

        Keyword::factory()->for($target)->named('shared')->create();

        $copy = app(CopyTestCase::class)($user, $case, TestSuite::factory()->for($target)->create());

        $this->assertSame(['shared'], $copy->keywords()->pluck('name')->all());
        $this->assertSame(2, Keyword::query()->where('test_project_id', $source->id)->count());
        $this->assertSame(1, Keyword::query()->where('test_project_id', $target->id)->count());
    }

    public function test_copying_a_suite_carries_the_keywords_of_its_cases()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $case = $this->caseIn($project, $suite);
        $case->keywords()->attach(Keyword::factory()->for($project)->named('smoke')->create());

        $copy = app(CopyTestSuite::class)($user, $suite);

        $this->assertSame(
            ['smoke'],
            $copy->testCases()->sole()->keywords()->pluck('name')->all(),
        );
    }

    /**
     * Keywords are on the case, so a new version cannot change them and there
     * is nothing to copy — the assignment the case already had still applies.
     */
    public function test_a_new_version_does_not_duplicate_the_cases_keywords()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $case = $this->caseIn($project, TestSuite::factory()->for($project)->create());
        $case->keywords()->attach(Keyword::factory()->for($project)->named('smoke')->create());

        app(CreateTestCaseVersion::class)($user, $case);

        $this->assertSame(['smoke'], $case->keywords()->pluck('name')->all());
        $this->assertSame(1, $case->keywords()->count());
    }

    private function caseIn(TestProject $project, TestSuite $suite): TestCaseModel
    {
        return TestCaseModel::factory()
            ->for($suite, 'testSuite')
            ->withVersion()
            ->create(['test_project_id' => $project->id]);
    }

    /**
     * Reading the source needs `view_test_cases` there and writing the copy
     * needs `manage_test_cases` in the target.
     */
    private function crossProjectUser(TestProject $source, TestProject $target): User
    {
        $user = User::factory()->for(Role::factory()->granting())->create();

        $this->assignProjectRole($user, $source, Ability::ViewTestCases);
        $this->assignProjectRole($user, $target, Ability::ManageTestCases);

        return $user;
    }
}
