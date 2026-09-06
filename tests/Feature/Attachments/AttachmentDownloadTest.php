<?php

namespace Tests\Feature\Attachments;

use App\Enums\Ability;
use App\Models\Attachment;
use App\Models\Role;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

/**
 * The endpoint legacy got most wrong, so the assertions here are mostly about
 * what it must refuse.
 */
class AttachmentDownloadTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attachments');
    }

    public function test_a_user_who_can_see_the_suite_can_download_its_file()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $attachment = Attachment::factory()->attachedTo($suite)->withContent('the bytes')->create();

        $response = $this->actingAs($user)->get(route('attachments.show', $attachment));

        $response->assertOk();
        $this->assertSame('the bytes', $response->streamedContent());
    }

    /**
     * The legacy hole, in one test.
     *
     * Its download endpoint asked only for a login. Ids were sequential and
     * nothing tied a file to a project the requester could see, so any user
     * could walk the range and take every attachment in the installation.
     */
    public function test_a_logged_in_user_with_no_standing_in_the_project_is_refused()
    {
        $theirs = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($theirs)->create();
        $attachment = Attachment::factory()->attachedTo($suite)->withContent()->create();

        $outsider = User::factory()->for(Role::factory()->granting())->create();

        $this->actingAs($outsider)
            ->get(route('attachments.show', $attachment))
            ->assertForbidden();
    }

    /**
     * Being able to see one project's cases must not reach another's files,
     * since the id says nothing about which project it belongs to.
     */
    public function test_standing_in_another_project_does_not_reach_this_file()
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::ViewTestCases, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($theirs)->create();
        $attachment = Attachment::factory()->attachedTo($suite)->withContent()->create();

        $this->actingAs($user)
            ->get(route('attachments.show', $attachment))
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_the_login_page()
    {
        $attachment = Attachment::factory()->withContent()->create();

        $this->get(route('attachments.show', $attachment))
            ->assertRedirect(route('login'));
    }

    /**
     * A test case's files are reached through the project of its case, so the
     * same check has to hold when the parent is a version.
     */
    public function test_a_versions_file_is_authorized_against_its_projects_ability()
    {
        $project = TestProject::factory()->create();
        $version = TestCaseModel::factory()
            ->withVersion()
            ->create(['test_project_id' => $project->id])
            ->versions()
            ->sole();
        $attachment = Attachment::factory()->attachedTo($version)->withContent()->create();

        $outsider = User::factory()->for(Role::factory()->granting())->create();
        $insider = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->actingAs($outsider)
            ->get(route('attachments.show', $attachment))
            ->assertForbidden();

        $this->actingAs($insider)
            ->get(route('attachments.show', $attachment))
            ->assertOk();
    }

    /**
     * Raster images are the only thing a browser is trusted to render in
     * place, and even they go out with `nosniff` so the decision cannot be
     * second-guessed from the content.
     */
    public function test_an_image_is_shown_in_place_under_its_own_type()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $attachment = Attachment::factory()->attachedTo($suite)->withContent()->create();

        $this->actingAs($user)
            ->get(route('attachments.show', $attachment))
            ->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('content-disposition', 'inline; filename='.$attachment->file_name);
    }

    /**
     * Legacy served every attachment inline under the type the uploader
     * claimed, so an uploaded page ran as script on this origin. Nothing but a
     * raster image is shown in place here, whatever it says it is.
     */
    public function test_anything_that_is_not_an_image_is_sent_as_a_download()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $attachment = Attachment::factory()
            ->attachedTo($suite)
            ->html()
            ->withContent('<script>alert(document.cookie)</script>')
            ->create();

        $response = $this->actingAs($user)->get(route('attachments.show', $attachment));

        $response
            ->assertOk()
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('content-security-policy', "default-src 'none'; sandbox");

        $this->assertStringStartsWith(
            'attachment;',
            (string) $response->headers->get('content-disposition'),
        );
    }

    /**
     * A row whose file has gone is a missing file, not a server error. A
     * database restored without its disk is the ordinary way to get here.
     */
    public function test_a_row_whose_file_is_gone_is_a_missing_file()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $attachment = Attachment::factory()->attachedTo($suite)->create();

        $this->actingAs($user)
            ->get(route('attachments.show', $attachment))
            ->assertNotFound();
    }
}
