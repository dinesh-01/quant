<?php

namespace Tests\Feature\CustomFields;

use App\Actions\TestPlans\DeleteTestPlan;
use App\Actions\TestProjects\DeleteTestProject;
use App\Actions\TestSpecification\CopyTestCase;
use App\Actions\TestSpecification\CopyTestSuite;
use App\Actions\TestSpecification\CreateTestCaseVersion;
use App\Actions\TestSpecification\DeleteTestCase;
use App\Actions\TestSpecification\DeleteTestCaseVersion;
use App\Actions\TestSpecification\DeleteTestSuite;
use App\Enums\Ability;
use App\Enums\CustomFieldEntity;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Execution;
use App\Models\Role;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

/**
 * What happens to answers when the thing they hang off is copied or deleted.
 *
 * Nothing here can be left to the database: the value table points at its
 * subject polymorphically, so no foreign key cascade reaches these rows and
 * every path has to say what it does with them.
 */
class CustomFieldLifecycleTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_a_new_version_starts_with_the_previous_answers()
    {
        $project = TestProject::factory()->create();
        $version = $this->version($project);
        $field = $this->fieldFor($project, CustomFieldEntity::TestCase);

        CustomFieldValue::factory()->about($version)->answering($field, 'Chrome')->create();

        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $next = app(CreateTestCaseVersion::class)($user, $version->testCase);

        $this->assertSame(2, $next->version);
        $this->assertSame('Chrome', $next->customFieldValues()->sole()->value);

        /* The version it was branched from keeps its own. */
        $this->assertSame(1, $version->customFieldValues()->count());
    }

    public function test_copying_a_case_copies_the_answers()
    {
        $project = TestProject::factory()->create();
        $version = $this->version($project);
        $field = $this->fieldFor($project, CustomFieldEntity::TestCase);

        CustomFieldValue::factory()->about($version)->answering($field, 'Chrome')->create();

        $target = TestSuite::factory()->for($project)->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);

        $copy = app(CopyTestCase::class)($user, $version->testCase, $target);

        $this->assertSame('Chrome', $copy->versions()->sole()->customFieldValues()->sole()->value);
    }

    /**
     * Definitions are shared but enablement is not, so a copy into a project
     * that does not record the field arrives without it rather than carrying an
     * answer to a question that project never asks.
     */
    public function test_copying_into_another_project_keeps_only_the_fields_it_records()
    {
        $source = TestProject::factory()->create();
        $target = TestProject::factory()->create();

        $version = $this->version($source);

        $shared = CustomField::factory()
            ->named('browser')
            ->forEntity(CustomFieldEntity::TestCase)
            ->create();

        $sourceOnly = CustomField::factory()
            ->named('platform')
            ->forEntity(CustomFieldEntity::TestCase)
            ->create();

        $shared->testProjects()->attach([$source->id, $target->id], ['is_active' => true]);
        $sourceOnly->testProjects()->attach($source, ['is_active' => true]);

        CustomFieldValue::factory()->about($version)->answering($shared, 'Chrome')->create();
        CustomFieldValue::factory()->about($version)->answering($sourceOnly, 'Linux')->create();

        $user = User::factory()->for(Role::factory()->granting())->create();
        $this->assignProjectRole($user, $source, Ability::ViewTestCases, Ability::ManageTestCases);
        $this->assignProjectRole($user, $target, Ability::ViewTestCases, Ability::ManageTestCases);

        $copy = app(CopyTestCase::class)(
            $user,
            $version->testCase,
            TestSuite::factory()->for($target)->create(),
        );

        $answers = $copy->versions()->sole()->customFieldValues()->get();

        $this->assertCount(1, $answers);
        $this->assertSame($shared->id, $answers->sole()->custom_field_id);
    }

    public function test_copying_a_suite_copies_its_own_answers()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $field = $this->fieldFor($project, CustomFieldEntity::TestSuite);

        CustomFieldValue::factory()->about($suite)->answering($field, 'Chrome')->create();

        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $copy = app(CopyTestSuite::class)($user, $suite);

        $this->assertSame('Chrome', $copy->customFieldValues()->sole()->value);
    }

    public function test_deleting_a_version_removes_its_answers()
    {
        $project = TestProject::factory()->create();
        $version = $this->version($project);
        $field = $this->fieldFor($project, CustomFieldEntity::TestCase);

        /* A second version, so the case survives the delete. */
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        app(CreateTestCaseVersion::class)($user, $version->testCase);

        CustomFieldValue::factory()->about($version)->answering($field, 'Chrome')->create();

        app(DeleteTestCaseVersion::class)($user, $version);

        $this->assertSame(0, CustomFieldValue::query()->count());
    }

    public function test_deleting_a_case_removes_the_answers_of_every_version()
    {
        $project = TestProject::factory()->create();
        $version = $this->version($project);
        $field = $this->fieldFor($project, CustomFieldEntity::TestCase);

        CustomFieldValue::factory()->about($version)->answering($field, 'Chrome')->create();

        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $next = app(CreateTestCaseVersion::class)($user, $version->testCase);

        $this->assertSame(2, CustomFieldValue::query()->count());

        app(DeleteTestCase::class)($user, $next->testCase);

        $this->assertSame(0, CustomFieldValue::query()->count());
    }

    public function test_deleting_a_suite_removes_the_answers_beneath_it()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $child = TestSuite::factory()->for($project)->create(['parent_id' => $suite->id]);

        $suiteField = $this->fieldFor($project, CustomFieldEntity::TestSuite);

        CustomFieldValue::factory()->about($suite)->answering($suiteField, 'Chrome')->create();
        CustomFieldValue::factory()->about($child)->answering($suiteField, 'Firefox')->create();

        $user = $this->userWhoCan($project, Ability::ManageTestCases);

        app(DeleteTestSuite::class)($user, $suite);

        $this->assertSame(0, CustomFieldValue::query()->count());
    }

    /**
     * The subtree, and only the subtree: the answers are found by walking the
     * suites being deleted rather than by asking the project for its cases,
     * which would take every answer in it along with one suite.
     */
    public function test_deleting_a_suite_leaves_a_sibling_suites_answers_alone()
    {
        $project = TestProject::factory()->create();
        $doomed = TestSuite::factory()->for($project)->create();
        $sibling = TestSuite::factory()->for($project)->create();

        $suiteField = $this->fieldFor($project, CustomFieldEntity::TestSuite);
        $caseField = $this->fieldFor($project, CustomFieldEntity::TestCase);

        $survivingCase = TestCaseModel::factory()->for($project)->for($sibling)->create();
        $survivingVersion = TestCaseVersion::factory()->for($survivingCase, 'testCase')->create();

        CustomFieldValue::factory()->about($doomed)->answering($suiteField, 'Chrome')->create();
        $keptSuiteAnswer = CustomFieldValue::factory()->about($sibling)->answering($suiteField, 'Firefox')->create();
        $keptCaseAnswer = CustomFieldValue::factory()->about($survivingVersion)->answering($caseField, 'Safari')->create();

        app(DeleteTestSuite::class)($this->userWhoCan($project, Ability::ManageTestCases), $doomed);

        $this->assertEqualsCanonicalizing(
            [$keptSuiteAnswer->id, $keptCaseAnswer->id],
            CustomFieldValue::query()->pluck('id')->all(),
        );
    }

    public function test_deleting_a_plan_removes_its_answers()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $field = $this->fieldFor($project, CustomFieldEntity::TestPlan);

        CustomFieldValue::factory()->about($plan)->answering($field, 'Chrome')->create();

        app(DeleteTestPlan::class)($this->userWhoCan($project, Ability::CreateTestPlans), $plan);

        $this->assertSame(0, CustomFieldValue::query()->count());
    }

    public function test_deleting_a_project_removes_every_answer_in_it()
    {
        $project = TestProject::factory()->create();
        $other = TestProject::factory()->create();

        $suite = TestSuite::factory()->for($project)->create();
        $version = $this->version($project);
        $plan = TestPlan::factory()->for($project)->create();

        $suiteField = $this->fieldFor($project, CustomFieldEntity::TestSuite);
        $caseField = $this->fieldFor($project, CustomFieldEntity::TestCase);
        $planField = $this->fieldFor($project, CustomFieldEntity::TestPlan);
        $executionField = $this->fieldFor($project, CustomFieldEntity::Execution);
        $execution = Execution::factory()->for($plan, 'testPlan')->create();

        CustomFieldValue::factory()->about($suite)->answering($suiteField, 'Chrome')->create();
        CustomFieldValue::factory()->about($version)->answering($caseField, 'Firefox')->create();
        CustomFieldValue::factory()->about($plan)->answering($planField, 'Safari')->create();
        CustomFieldValue::factory()->about($execution)->answering($executionField, 'Edge')->create();

        /* Another project's answer to the same kind of field, which stays. */
        $elsewhere = TestSuite::factory()->for($other)->create();
        $kept = CustomFieldValue::factory()->about($elsewhere)->answering($suiteField, 'Edge')->create();

        $administrator = User::factory()
            ->for(Role::factory()->granting(Ability::ManageTestProjects), 'role')
            ->create();

        app(DeleteTestProject::class)($administrator, $project);

        $remaining = CustomFieldValue::query()->sole();

        $this->assertSame($kept->id, $remaining->id);
    }

    private function fieldFor(TestProject $project, CustomFieldEntity $entity): CustomField
    {
        return CustomField::factory()
            ->forEntity($entity)
            ->enabledIn($project)
            ->create();
    }

    private function version(TestProject $project): TestCaseVersion
    {
        $suite = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($project)->for($suite)->create();

        return TestCaseVersion::factory()->for($case, 'testCase')->create();
    }
}
