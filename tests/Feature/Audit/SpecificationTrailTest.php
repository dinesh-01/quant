<?php

namespace Tests\Feature\Audit;

use App\Actions\TestSpecification\CopyTestSuite;
use App\Actions\TestSpecification\CreateTestCase;
use App\Actions\TestSpecification\CreateTestCaseVersion;
use App\Actions\TestSpecification\FreezeTestCaseVersion;
use App\Actions\TestSpecification\UnfreezeTestCaseVersion;
use App\Actions\TestSpecification\UpdateTestCaseStep;
use App\Actions\TestSpecification\UpdateTestCaseVersion;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseStep;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecificationTrailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The first version is part of creating a case rather than an act of its
     * own, so recording both would tell a reader nothing the first line did.
     */
    public function test_creating_a_case_records_one_act_not_two()
    {
        $suite = TestSuite::factory()->create();

        app(CreateTestCase::class)($this->author(), $suite, ['name' => 'Login works']);

        $this->assertSame(1, AuditEvent::query()->count());
        $this->assertSame(
            AuditAction::TestCaseCreated->value,
            AuditEvent::query()->sole()->action,
        );
    }

    /**
     * A version has no name, so pointing the log at one would show a bare id.
     * The named case is the subject and the version number is a property.
     */
    public function test_a_version_act_is_recorded_against_the_named_case()
    {
        $case = TestCaseModel::factory()->withVersion()->create(['name' => 'Login works']);

        app(CreateTestCaseVersion::class)($this->author(), $case);

        $event = AuditEvent::query()
            ->where('action', AuditAction::TestCaseVersionCreated->value)
            ->sole();

        $this->assertSame(TestCaseModel::class, $event->subject_type);
        $this->assertSame($case->getKey(), $event->subject_id);
        $this->assertSame(2, $event->properties['version']);
    }

    /**
     * `updater_id` on the row only ever names the last editor, so it cannot
     * answer who made a particular change. The trail is what gives the
     * sequence — and the updater itself is churn a reader does not want.
     */
    public function test_a_version_edit_records_the_change_but_not_the_updater()
    {
        $case = TestCaseModel::factory()->withVersion()->create();
        $version = $case->latestVersion;
        $version->update(['summary' => 'Original']);

        app(UpdateTestCaseVersion::class)($this->author(), $version, ['summary' => 'Revised']);

        $event = AuditEvent::query()
            ->where('action', AuditAction::TestCaseVersionUpdated->value)
            ->sole();

        $this->assertSame('Original', $event->properties['summary']['from']);
        $this->assertSame('Revised', $event->properties['summary']['to']);
        $this->assertArrayNotHasKey('updater_id', $event->properties);
    }

    /**
     * Unfreezing undoes the protection an execution relies on: afterwards the
     * content can change under results already recorded against it.
     */
    public function test_freezing_and_unfreezing_are_both_recorded()
    {
        $case = TestCaseModel::factory()->withVersion()->create();
        $freezer = $this->freezer();

        app(FreezeTestCaseVersion::class)($freezer, $case->latestVersion);
        app(UnfreezeTestCaseVersion::class)($freezer, $case->latestVersion->refresh());

        $this->assertSame(1, AuditEvent::query()
            ->where('action', AuditAction::TestCaseVersionFrozen->value)->count());
        $this->assertSame(1, AuditEvent::query()
            ->where('action', AuditAction::TestCaseVersionUnfrozen->value)->count());
    }

    /**
     * Freezing an already frozen version is a no-op, and a no-op is not an act.
     */
    public function test_freezing_an_already_frozen_version_records_nothing_further()
    {
        $case = TestCaseModel::factory()->withVersion()->create();
        $freezer = $this->freezer();

        app(FreezeTestCaseVersion::class)($freezer, $case->latestVersion);
        app(FreezeTestCaseVersion::class)($freezer, $case->latestVersion->refresh());

        $this->assertSame(1, AuditEvent::query()
            ->where('action', AuditAction::TestCaseVersionFrozen->value)->count());
    }

    /**
     * A step's expected result is what a tester is judged against, so a silent
     * change to it is the one content edit worth attributing exactly — the
     * version's `updater_id` does not even move when a step changes.
     */
    public function test_editing_a_step_records_the_expected_result_change()
    {
        $case = TestCaseModel::factory()->withVersion()->create();
        $step = TestCaseStep::factory()->for($case->latestVersion, 'testCaseVersion')->create([
            'expected_results' => 'Signed in',
        ]);

        app(UpdateTestCaseStep::class)($this->author(), $step, ['expected_results' => 'Signed in and redirected']);

        $event = AuditEvent::query()
            ->where('action', AuditAction::TestCaseStepUpdated->value)
            ->sole();

        $this->assertSame($case->getKey(), $event->subject_id);
        $this->assertSame('Signed in', $event->properties['expected_results']['from']);
        $this->assertSame($step->sort_order, $event->properties['step']);
    }

    /**
     * A subtree copy is a single act with a single intent. One record per
     * copied node would bury the acts a reader is looking for.
     */
    public function test_copying_a_subtree_records_one_act_for_the_whole_copy()
    {
        $project = TestProject::factory()->create();
        $source = TestSuite::factory()->for($project)->create();
        $child = TestSuite::factory()->for($project)->create(['parent_id' => $source->getKey()]);
        TestCaseModel::factory()->count(2)->for($project)->for($child, 'testSuite')->create();

        app(CopyTestSuite::class)($this->author(), $source, null, $project);

        $this->assertSame(1, AuditEvent::query()->count());
        $this->assertSame(
            AuditAction::TestSuiteCopied->value,
            AuditEvent::query()->sole()->action,
        );
    }

    /**
     * Reordering is presentation, and it is the highest-volume write in the
     * specification. Recording it would drown the acts that matter.
     */
    public function test_reordering_is_deliberately_not_recorded()
    {
        $this->assertEmpty(array_filter(
            AuditAction::cases(),
            fn (AuditAction $action): bool => str_contains($action->value, 'reorder'),
        ));
    }

    private function author(): User
    {
        return User::factory()
            ->for(Role::factory()->granting(Ability::ViewTestCases, Ability::ManageTestCases), 'role')
            ->create();
    }

    private function freezer(): User
    {
        return User::factory()
            ->for(Role::factory()->granting(
                Ability::ViewTestCases,
                Ability::ManageTestCases,
                Ability::FreezeTestCases,
            ), 'role')
            ->create();
    }
}
