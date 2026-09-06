<?php

namespace Tests\Feature\Attachments;

use App\Enums\Ability;
use App\Models\Attachment;
use App\Models\Role;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

/**
 * The screens that list attachments.
 *
 * The upload, download and lifecycle rules are covered by the sibling tests
 * here; what these pin down is that each screen is actually handed the files it
 * is meant to show, and that the page — not the browser, and not the file name
 * — decides which of them may be rendered in place.
 */
class AttachmentScreensTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attachments');
    }

    public function test_a_selected_suite_lists_its_attachments()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        Attachment::factory()->for($suite, 'attachable')->create([
            'title' => 'The login screen',
            'file_name' => 'login.png',
            'mime_type' => 'image/png',
        ]);

        $this->actingAs($user)
            ->get(route('specification.suites.show', [$project, $suite]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected.suite.attachments.0.label', 'The login screen')
                ->where('selected.suite.attachments.0.is_image', true)
                ->where('attachmentRules.max_kilobytes', (int) config('attachments.max_kilobytes'))
            );
    }

    /**
     * Files hang off the version, so reading an older version has to show the
     * evidence of *that* revision rather than the newest one's.
     */
    public function test_each_version_lists_only_its_own_attachments()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $case = TestCaseModel::factory()->create(['test_project_id' => $project->id]);
        $first = TestCaseVersion::factory()->for($case, 'testCase')->version(1)->create();
        $second = TestCaseVersion::factory()->for($case, 'testCase')->version(2)->create();

        Attachment::factory()->for($first, 'attachable')->create(['title' => 'First run']);
        Attachment::factory()->for($second, 'attachable')->create(['title' => 'Second run']);

        $this->actingAs($user)
            ->get(route('specification.cases.show', [$project, $case]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->count('selected.case.version.attachments', 1)
                ->where('selected.case.version.attachments.0.label', 'Second run')
            );

        $this->actingAs($user)
            ->get(route('specification.cases.show', [$project, $case]).'?version=1')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->count('selected.case.version.attachments', 1)
                ->where('selected.case.version.attachments.0.label', 'First run')
            );
    }

    /**
     * `is_image` comes from the sniffed type, which is the only thing that
     * decides whether the browser is asked to render the bytes. A screen that
     * worked it out from the file name would be trusting the uploader.
     */
    public function test_a_document_named_like_an_image_is_not_offered_for_display()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        Attachment::factory()->for($suite, 'attachable')->create([
            'title' => null,
            'file_name' => 'report.png',
            'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($user)
            ->get(route('specification.suites.show', [$project, $suite]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected.suite.attachments.0.label', 'report.png')
                ->where('selected.suite.attachments.0.is_image', false)
            );
    }

    public function test_the_project_screen_lists_the_projects_own_attachments()
    {
        $project = TestProject::factory()->create();
        $user = $this->administrator();

        Attachment::factory()->for($project, 'attachable')->create(['title' => 'Test strategy']);

        $this->actingAs($user)
            ->get(route('projects.edit', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('project.attachments.0.label', 'Test strategy')
                ->has('attachmentRules.extensions')
            );
    }

    /**
     * Two queries whatever a node carries: the files, and their uploaders.
     * Reading `$attachment->uploader?->name` without eager loading is the
     * obvious way to write this and costs a query per file.
     */
    public function test_listing_attachments_does_not_query_per_file()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();

        Attachment::factory()->count(2)->for($suite, 'attachable')->create();
        $few = $this->attachmentQueries($project, $suite);

        Attachment::factory()->count(10)->for($suite, 'attachable')->create();

        $this->assertSame($few, $this->attachmentQueries($project, $suite));
    }

    /**
     * Counts only the reads of the two attachment tables, so the page's other
     * queries cannot mask a change here.
     */
    private function attachmentQueries(TestProject $project, TestSuite $suite): int
    {
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)
            ->get(route('specification.suites.show', [$project, $suite]))
            ->assertOk();

        $queries = array_column(DB::getQueryLog(), 'query');

        DB::disableQueryLog();

        return count(array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, 'attachments')
                || str_contains($query, 'from `users`'),
        ));
    }

    private function administrator(): User
    {
        return User::factory()->create([
            'role_id' => Role::factory()->create([
                'abilities' => [Ability::ManageTestProjects, Ability::ViewTestCases],
            ])->id,
        ]);
    }
}
