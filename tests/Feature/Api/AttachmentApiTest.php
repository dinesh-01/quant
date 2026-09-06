<?php

namespace Tests\Feature\Api;

use App\Enums\Ability;
use App\Models\Attachment;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class AttachmentApiTest extends TestCase
{
    use AuthenticatesApiTokens;
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attachments');
    }

    public function test_uploads_a_file_to_a_version_and_returns_201(): void
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($suite, 'testSuite')->create();
        $version = TestCaseVersion::factory()->for($case, 'testCase')->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);

        $this->apiAs($user)
            ->post(route('api.v1.attachments.versions.store', $version), [
                'file' => UploadedFile::fake()->image('evidence.png'),
                'title' => 'Failure shot',
            ])
            ->assertCreated()
            ->assertJsonPath('data.file_name', 'evidence.png')
            ->assertJsonPath('data.title', 'Failure shot');

        $attachment = Attachment::query()->sole();
        $this->assertSame(TestCaseVersion::class, $attachment->attachable_type);
        $this->assertSame($version->id, $attachment->attachable_id);
        Storage::disk('attachments')->assertExists($attachment->disk_path);
    }

    public function test_returns_403_when_uploading_without_manage(): void
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($suite, 'testSuite')->create();
        $version = TestCaseVersion::factory()->for($case, 'testCase')->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->apiAs($user)
            ->post(route('api.v1.attachments.versions.store', $version), [
                'file' => UploadedFile::fake()->image('evidence.png'),
            ])
            ->assertForbidden();
    }

    public function test_downloads_an_attachment_the_caller_can_view(): void
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);

        $this->apiAs($user)
            ->post(route('api.v1.attachments.suites.store', $suite), [
                'file' => UploadedFile::fake()->image('shot.png'),
            ])
            ->assertCreated();

        $attachment = Attachment::query()->sole();

        $this->apiAs($user)
            ->get(route('api.v1.attachments.show', $attachment))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
