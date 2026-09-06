<?php

namespace Tests\Feature\Attachments;

use App\Actions\Attachments\StoreAttachment;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class AttachmentUploadTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attachments');
    }

    public function test_a_file_can_be_attached_to_a_test_suite()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('attachments.suites.store', $suite), [
                'file' => UploadedFile::fake()->image('login-screen.png'),
                'title' => 'The login screen',
            ])
            ->assertSessionHasNoErrors();

        $attachment = Attachment::query()->sole();

        $this->assertSame(TestSuite::class, $attachment->attachable_type);
        $this->assertSame($suite->id, $attachment->attachable_id);
        $this->assertSame($user->id, $attachment->uploader_id);
        $this->assertSame('The login screen', $attachment->title);
        $this->assertSame('login-screen.png', $attachment->file_name);
        Storage::disk('attachments')->assertExists($attachment->disk_path);
    }

    public function test_a_file_can_be_attached_to_a_test_case_version()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $version = $this->versionIn($project);

        $this->actingAs($user)
            ->post(route('attachments.versions.store', $version), [
                'file' => UploadedFile::fake()->image('evidence.png'),
            ])
            ->assertSessionHasNoErrors();

        $attachment = Attachment::query()->sole();

        $this->assertSame(TestCaseVersion::class, $attachment->attachable_type);
        $this->assertSame($version->id, $attachment->attachable_id);
        $this->assertNull($attachment->title);
    }

    /**
     * A project's own files answer to `manage_test_projects`, which only a
     * global role may grant — the same standing legacy required to reach the
     * project edit page these were uploaded from.
     */
    public function test_a_file_can_be_attached_to_a_test_project()
    {
        $project = TestProject::factory()->create();
        $user = User::factory()
            ->for(Role::factory()->granting(Ability::ManageTestProjects))
            ->create();

        $this->actingAs($user)
            ->post(route('attachments.projects.store', $project), [
                'file' => UploadedFile::fake()->image('conventions.png'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(TestProject::class, Attachment::query()->sole()->attachable_type);
    }

    public function test_a_file_can_be_attached_to_a_test_plan()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCan($project, Ability::CreateTestPlans, Ability::ViewExecutions);

        $this->actingAs($user)
            ->post(route('attachments.plans.store', $plan), [
                'file' => UploadedFile::fake()->image('plan-notes.png'),
            ])
            ->assertSessionHasNoErrors();

        $attachment = Attachment::query()->sole();

        $this->assertSame(TestPlan::class, $attachment->attachable_type);
        $this->assertSame($plan->id, $attachment->attachable_id);
    }

    public function test_a_file_can_be_attached_to_an_execution()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCan($project, Ability::ExecuteTests, Ability::ViewExecutions);
        $execution = Execution::factory()->for($plan, 'testPlan')->create(['tester_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('attachments.executions.store', $execution), [
                'file' => UploadedFile::fake()->image('run-evidence.png'),
            ])
            ->assertSessionHasNoErrors();

        $attachment = Attachment::query()->sole();

        $this->assertSame(Execution::class, $attachment->attachable_type);
        $this->assertSame($execution->id, $attachment->attachable_id);
    }

    public function test_attaching_to_a_plan_requires_create_test_plans()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCan($project, Ability::ViewExecutions);

        $this->actingAs($user)
            ->post(route('attachments.plans.store', $plan), [
                'file' => UploadedFile::fake()->image('plan-notes.png'),
            ])
            ->assertForbidden();
    }

    /**
     * The name on disk is generated, so nothing the uploader chose is ever
     * used as a path.
     */
    public function test_the_uploaders_file_name_is_not_used_on_disk()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('attachments.suites.store', $suite), [
                'file' => UploadedFile::fake()->image('../../secrets.png'),
            ])
            ->assertSessionHasNoErrors();

        $attachment = Attachment::query()->sole();

        $this->assertSame('secrets.png', $attachment->file_name);
        $this->assertSame('test-suites/'.$suite->id, dirname($attachment->disk_path));
        $this->assertStringNotContainsString('..', $attachment->disk_path);
        Storage::disk('attachments')->assertExists($attachment->disk_path);
    }

    /**
     * The stored type comes from the file's own bytes, never from the type the
     * browser claimed. Legacy stored the claim and echoed it back on download,
     * which is what let an uploaded page run on its origin.
     *
     * Driven through the action rather than the endpoint because Laravel's fake
     * uploads report a type instead of having one — only a real `UploadedFile`
     * over real bytes can tell the two apart.
     */
    public function test_the_stored_type_is_read_from_the_file_and_not_from_the_request()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        $attachment = app(StoreAttachment::class)(
            $user,
            $suite,
            $this->realUpload('holiday.png', claiming: 'text/html'),
        );

        $this->assertSame('image/png', $attachment->mime_type);
    }

    public function test_an_upload_is_recorded_in_the_audit_trail()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('attachments.suites.store', $suite), [
                'file' => UploadedFile::fake()->image('evidence.png'),
            ])
            ->assertSessionHasNoErrors();

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::AttachmentUploaded->value, $event->action);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('evidence.png', $event->properties['file_name']);
        $this->assertSame(TestSuite::class, $event->properties['attached_to']);
        $this->assertSame($suite->id, $event->properties['attached_to_id']);
    }

    public function test_a_file_larger_than_the_limit_is_refused()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        $oversize = (int) config('attachments.max_kilobytes') + 1;

        $this->actingAs($user)
            ->post(route('attachments.suites.store', $suite), [
                'file' => UploadedFile::fake()->create('huge.pdf', $oversize, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Attachment::query()->count());
    }

    public function test_a_file_whose_contents_are_not_an_accepted_type_is_refused()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('attachments.suites.store', $suite), [
                'file' => UploadedFile::fake()->create('screenshot.png', 4, 'text/html'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Attachment::query()->count());
        Storage::disk('attachments')->assertDirectoryEmpty('/');
    }

    public function test_a_file_whose_name_is_not_an_accepted_type_is_refused()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('attachments.suites.store', $suite), [
                'file' => UploadedFile::fake()->create('shell.phtml', 4, 'image/png'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Attachment::query()->count());
    }

    public function test_uploading_requires_the_parents_manage_ability()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('attachments.suites.store', $suite), [
                'file' => UploadedFile::fake()->image('evidence.png'),
            ])
            ->assertForbidden();

        $this->assertSame(0, Attachment::query()->count());
    }

    /**
     * Abilities are held per project, so being able to manage one project's
     * cases must not reach another project's suites.
     */
    public function test_uploading_is_refused_for_a_suite_in_another_project()
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($theirs)->create();

        $this->actingAs($user)
            ->post(route('attachments.suites.store', $suite), [
                'file' => UploadedFile::fake()->image('evidence.png'),
            ])
            ->assertForbidden();
    }

    public function test_an_attachment_can_be_removed_with_its_file()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $attachment = Attachment::factory()->attachedTo($suite)->withContent()->create();

        $this->actingAs($user)
            ->delete(route('attachments.destroy', $attachment))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Attachment::query()->count());
        Storage::disk('attachments')->assertMissing($attachment->disk_path);

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::AttachmentDeleted->value, $event->action);
        $this->assertSame($attachment->file_name, $event->properties['file_name']);
    }

    public function test_removing_requires_the_parents_manage_ability()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $attachment = Attachment::factory()->attachedTo($suite)->withContent()->create();

        $this->actingAs($user)
            ->delete(route('attachments.destroy', $attachment))
            ->assertForbidden();

        $this->assertSame(1, Attachment::query()->count());
        Storage::disk('attachments')->assertExists($attachment->disk_path);
    }

    private function versionIn(TestProject $project): TestCaseVersion
    {
        return TestCaseModel::factory()
            ->withVersion()
            ->create(['test_project_id' => $project->id])
            ->versions()
            ->sole();
    }

    /**
     * A genuine upload of real image bytes, claiming to be something else.
     *
     * The bytes come from the framework's own fake image generator, so the
     * test does not carry an encoded blob around.
     */
    private function realUpload(string $name, string $claiming): UploadedFile
    {
        /* Held in a variable: the fake deletes its temp file once collected. */
        $source = UploadedFile::fake()->image('source.png');

        $bytes = (string) file_get_contents((string) $source->getRealPath());

        $path = (string) tempnam(sys_get_temp_dir(), 'attachment-test');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, $claiming, test: true);
    }
}
