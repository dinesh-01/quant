<?php

namespace Tests\Feature\Attachments;

use App\Actions\Executions\DeleteExecution;
use App\Actions\TestPlans\DeleteTestPlan;
use App\Actions\TestProjects\DeleteTestProject;
use App\Actions\TestSpecification\CopyTestCase;
use App\Actions\TestSpecification\CopyTestSuite;
use App\Actions\TestSpecification\CreateTestCaseVersion;
use App\Actions\TestSpecification\DeleteTestCase;
use App\Actions\TestSpecification\DeleteTestCaseVersion;
use App\Actions\TestSpecification\DeleteTestSuite;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Attachment;
use App\Models\AuditEvent;
use App\Models\Execution;
use App\Models\Role;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

/**
 * What happens to files when the thing they hang off is deleted or copied.
 *
 * A polymorphic link carries no foreign key, so nothing in the database
 * cascades to these rows — every delete action has to purge them itself, and a
 * missed one leaves rows pointing at ids that no longer exist and files nobody
 * can reach. Legacy had exactly that gap, so each parent gets its own test.
 */
class AttachmentLifecycleTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attachments');
    }

    public function test_deleting_a_version_removes_its_attachments()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $case = $this->caseIn($project);
        $version = app(CreateTestCaseVersion::class)($user, $case);
        $attachment = Attachment::factory()->attachedTo($version)->withContent()->create();

        app(DeleteTestCaseVersion::class)($user, $version);

        $this->assertGone($attachment);
    }

    public function test_deleting_a_case_removes_the_attachments_of_every_version()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $case = $this->caseIn($project);

        Attachment::factory()->attachedTo($case->versions()->sole())->withContent()->create();

        /* The new version takes a copy, so there is one file per version. */
        app(CreateTestCaseVersion::class)($user, $case);

        $paths = Attachment::query()->pluck('disk_path');
        $this->assertCount(2, $paths);

        app(DeleteTestCase::class)($user, $case);

        $this->assertSame(0, Attachment::query()->count());

        foreach ($paths as $path) {
            Storage::disk('attachments')->assertMissing($path);
        }
    }

    /**
     * A suite delete cascades through nested suites, their cases and those
     * cases' versions in the database, so the purge has to reach as far.
     */
    public function test_deleting_a_suite_removes_the_attachments_of_its_whole_subtree()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $parent = TestSuite::factory()->for($project)->create();
        $child = TestSuite::factory()->childOf($parent)->create();
        $version = TestCaseModel::factory()
            ->for($child, 'testSuite')
            ->withVersion()
            ->create(['test_project_id' => $project->id])
            ->versions()
            ->sole();

        $onParent = Attachment::factory()->attachedTo($parent)->withContent()->create();
        $onChild = Attachment::factory()->attachedTo($child)->withContent()->create();
        $onVersion = Attachment::factory()->attachedTo($version)->withContent()->create();

        app(DeleteTestSuite::class)($user, $parent);

        $this->assertGone($onParent);
        $this->assertGone($onChild);
        $this->assertGone($onVersion);
    }

    public function test_deleting_a_project_removes_every_attachment_in_it()
    {
        $project = TestProject::factory()->create();
        $user = User::factory()
            ->for(Role::factory()->granting(Ability::ManageTestProjects))
            ->create();
        $suite = TestSuite::factory()->for($project)->create();
        $version = TestCaseModel::factory()
            ->for($suite, 'testSuite')
            ->withVersion()
            ->create(['test_project_id' => $project->id])
            ->versions()
            ->sole();

        $plan = TestPlan::factory()->for($project)->create();
        $execution = Execution::factory()->for($plan, 'testPlan')->create();

        $onProject = Attachment::factory()->attachedTo($project)->withContent()->create();
        $onSuite = Attachment::factory()->attachedTo($suite)->withContent()->create();
        $onVersion = Attachment::factory()->attachedTo($version)->withContent()->create();
        $onPlan = Attachment::factory()->attachedTo($plan)->withContent()->create();
        $onExecution = Attachment::factory()->attachedTo($execution)->withContent()->create();

        app(DeleteTestProject::class)($user, $project);

        $this->assertGone($onProject);
        $this->assertGone($onSuite);
        $this->assertGone($onVersion);
        $this->assertGone($onPlan);
        $this->assertGone($onExecution);
    }

    public function test_deleting_a_plan_removes_its_attachments_and_those_of_its_runs()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCan($project, Ability::CreateTestPlans);
        $execution = Execution::factory()->for($plan, 'testPlan')->create();
        $onPlan = Attachment::factory()->attachedTo($plan)->withContent()->create();
        $onExecution = Attachment::factory()->attachedTo($execution)->withContent()->create();

        app(DeleteTestPlan::class)($user, $plan);

        $this->assertGone($onPlan);
        $this->assertGone($onExecution);
    }

    public function test_deleting_an_execution_removes_its_attachments()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCan($project, Ability::DeleteExecutions);
        $execution = Execution::factory()->for($plan, 'testPlan')->completed()->create();
        $attachment = Attachment::factory()->attachedTo($execution)->withContent()->create();

        app(DeleteExecution::class)($user, $execution);

        $this->assertGone($attachment);
    }

    /**
     * Deleting something that holds no files must not reach anyone else's.
     * `PurgeAttachments` builds its `where` from the ids it found, and an empty
     * one would constrain nothing at all.
     */
    public function test_deleting_a_suite_with_no_files_leaves_other_attachments_alone()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $empty = TestSuite::factory()->for($project)->create();
        $other = TestSuite::factory()->for($project)->create();
        $attachment = Attachment::factory()->attachedTo($other)->withContent()->create();

        app(DeleteTestSuite::class)($user, $empty);

        $this->assertSame(1, Attachment::query()->count());
        Storage::disk('attachments')->assertExists($attachment->disk_path);
    }

    public function test_a_delete_records_how_many_attachments_went_with_it()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        Attachment::factory()->count(2)->attachedTo($suite)->withContent()->create();

        app(DeleteTestSuite::class)($user, $suite);

        $event = AuditEvent::query()
            ->where('action', AuditAction::TestSuiteDeleted->value)
            ->sole();

        $this->assertSame(2, $event->properties['attachments']);
    }

    /**
     * A new version keeps the evidence the old one carried — usually the
     * screenshots the version is about — which is what legacy did too.
     */
    public function test_a_new_version_takes_a_copy_of_the_sources_attachments()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $case = $this->caseIn($project);
        $source = $case->versions()->sole();
        $attachment = Attachment::factory()
            ->attachedTo($source)
            ->titled('The failing screen')
            ->withContent('the bytes')
            ->create();

        $version = app(CreateTestCaseVersion::class)($user, $case);

        $copy = $this->soleAttachmentOf($version);

        $this->assertSame('The failing screen', $copy->title);
        $this->assertSame($attachment->file_name, $copy->file_name);
        $this->assertSame($attachment->uploader_id, $copy->uploader_id);
        $this->assertSame('the bytes', Storage::disk('attachments')->get($copy->disk_path));
    }

    /**
     * Each copy owns its bytes. Two rows pointing at one file would mean
     * deleting either copy silently breaks the other, and `DeleteAttachment`
     * has no way to know it is not the last reference.
     */
    public function test_a_copied_attachment_does_not_share_a_file_with_its_source()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $case = $this->caseIn($project);
        $attachment = Attachment::factory()
            ->attachedTo($case->versions()->sole())
            ->withContent()
            ->create();

        $version = app(CreateTestCaseVersion::class)($user, $case);

        $copy = $this->soleAttachmentOf($version);

        $this->assertNotSame($attachment->disk_path, $copy->disk_path);

        app(DeleteTestCaseVersion::class)($user, $version);

        Storage::disk('attachments')->assertExists($attachment->disk_path);
    }

    public function test_copying_a_case_copies_the_attachments_of_every_version()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $case = $this->caseIn($project);
        $target = TestSuite::factory()->for($project)->create();
        Attachment::factory()->attachedTo($case->versions()->sole())->withContent()->create();

        $copy = app(CopyTestCase::class)($user, $case, $target);

        $this->assertSame(2, Attachment::query()->count());
        $this->assertNotNull($this->soleAttachmentOf($copy->versions()->sole()));
    }

    public function test_copying_a_suite_copies_the_files_on_the_suite_and_on_its_cases()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $version = TestCaseModel::factory()
            ->for($suite, 'testSuite')
            ->withVersion()
            ->create(['test_project_id' => $project->id])
            ->versions()
            ->sole();

        Attachment::factory()->attachedTo($suite)->withContent()->create();
        Attachment::factory()->attachedTo($version)->withContent()->create();

        $copy = app(CopyTestSuite::class)($user, $suite);

        $this->assertNotNull($this->soleAttachmentOf($copy));
        $this->assertNotNull(
            $this->soleAttachmentOf($copy->testCases()->sole()->versions()->sole()),
        );
    }

    /**
     * A row whose file has gone is skipped rather than copied as a row
     * pointing at nothing, which would spread the breakage.
     */
    public function test_a_source_row_with_no_file_is_not_copied()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $case = $this->caseIn($project);
        Attachment::factory()->attachedTo($case->versions()->sole())->create();

        $version = app(CreateTestCaseVersion::class)($user, $case);

        $this->assertSame(0, $version->attachments()->count());
    }

    private function caseIn(TestProject $project): TestCaseModel
    {
        $suite = TestSuite::factory()->for($project)->create();

        return TestCaseModel::factory()
            ->for($suite, 'testSuite')
            ->withVersion()
            ->create(['test_project_id' => $project->id]);
    }

    private function soleAttachmentOf(TestSuite|TestCaseVersion $parent): Attachment
    {
        /** @var Attachment $attachment */
        $attachment = $parent->attachments()->sole();

        return $attachment;
    }

    private function assertGone(Attachment $attachment): void
    {
        $this->assertNull(Attachment::query()->find($attachment->id));
        Storage::disk('attachments')->assertMissing($attachment->disk_path);
    }
}
