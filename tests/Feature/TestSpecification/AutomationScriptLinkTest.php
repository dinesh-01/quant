<?php

namespace Tests\Feature\TestSpecification;

use App\Actions\TestSpecification\CopyTestCase;
use App\Actions\TestSpecification\CreateTestCaseVersion;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\CodeTracker;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseScriptLink;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AutomationScriptLinkTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_a_script_can_be_linked_to_a_version(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $version = $this->versionIn($project);

        $this->actingAs($user)
            ->post(route('script-links.store', $version), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $link = TestCaseScriptLink::query()->sole();

        $this->assertSame($version->id, $link->test_case_version_id);
        $this->assertSame('qa-scripts', $link->repository);
        $this->assertSame('spec/login_spec.rb', $link->path);
        $this->assertSame('main', $link->branch);
    }

    public function test_linking_a_script_is_recorded_in_the_audit_trail(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $version = $this->versionIn($project);

        $this->actingAs($user)
            ->post(route('script-links.store', $version), $this->payload())
            ->assertSessionHasNoErrors();

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::AutomationScriptLinked->value, $event->action);
        $this->assertSame($version->test_case_id, $event->subject_id);
        $this->assertSame('qa-scripts', $event->properties['repository']);
    }

    public function test_the_same_script_cannot_be_linked_twice(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $version = $this->versionIn($project);
        TestCaseScriptLink::factory()->for($version, 'testCaseVersion')->create($this->payload());

        $this->actingAs($user)
            ->post(route('script-links.store', $version), $this->payload())
            ->assertSessionHasErrors('path');

        $this->assertSame(1, TestCaseScriptLink::query()->count());
    }

    public function test_linking_requires_managing_test_cases(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageCodeTrackers);
        $version = $this->versionIn($project);

        $this->actingAs($user)
            ->post(route('script-links.store', $version), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, TestCaseScriptLink::query()->count());
    }

    public function test_a_script_can_be_removed(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $version = $this->versionIn($project);
        $link = TestCaseScriptLink::factory()->for($version, 'testCaseVersion')->create();

        $this->actingAs($user)
            ->delete(route('script-links.destroy', $link))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, TestCaseScriptLink::query()->count());
    }

    public function test_the_case_pane_lists_script_links_and_a_browse_url(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $version = $this->versionIn($project);
        TestCaseScriptLink::factory()->for($version, 'testCaseVersion')->create([
            'repository' => 'qa-scripts',
            'path' => 'spec/login_spec.rb',
            'branch' => 'main',
        ]);
        CodeTracker::factory()->for($project)->create([
            'view_url_template' => '{base_url}/{repository}/blob/{branch}/{path}',
            'base_url' => 'https://github.com',
        ]);

        $this->actingAs($user)
            ->get(route('specification.cases.show', [$project, $version->testCase]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('selected.case.version.script_links', 1)
                ->where('selected.case.version.script_links.0.repository', 'qa-scripts')
                ->where(
                    'selected.case.version.script_links.0.url',
                    'https://github.com/qa-scripts/blob/main/spec/login_spec.rb',
                )
            );
    }

    public function test_a_new_version_copies_the_script_links_of_its_source(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $version = $this->versionIn($project);
        TestCaseScriptLink::factory()->for($version, 'testCaseVersion')->create($this->payload());

        $copy = app(CreateTestCaseVersion::class)($user, $version->testCase, $version);

        $this->assertSame(
            ['qa-scripts'],
            $copy->scriptLinks()->pluck('repository')->all(),
        );
        $this->assertSame(
            ['spec/login_spec.rb'],
            $copy->scriptLinks()->pluck('path')->all(),
        );
        $this->assertSame(1, $version->refresh()->scriptLinks()->count(), 'the source keeps its own links');
        $this->assertNotSame($version->scriptLinks->sole()->id, $copy->scriptLinks->sole()->id);
    }

    public function test_copying_a_case_copies_script_links_on_each_version(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $target = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($suite, 'testSuite')->create();
        $version = TestCaseVersion::factory()->for($case, 'testCase')->create();
        TestCaseScriptLink::factory()->for($version, 'testCaseVersion')->create($this->payload());

        $copy = app(CopyTestCase::class)($user, $case->refresh(), $target);

        $this->assertSame(
            ['spec/login_spec.rb'],
            $copy->versions->first()->scriptLinks->pluck('path')->all(),
        );
        $this->assertSame(1, $version->refresh()->scriptLinks()->count());
    }

    /**
     * @param  array<string, string|null>  $overrides
     * @return array{project_key: string, repository: string, path: string, branch: string|null, commit: string|null}
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'project_key' => 'acme/payments',
            'repository' => 'qa-scripts',
            'path' => 'spec/login_spec.rb',
            'branch' => 'main',
            'commit' => null,
        ], $overrides);
    }

    private function versionIn(TestProject $project): TestCaseVersion
    {
        $suite = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($suite, 'testSuite')->create();

        return TestCaseVersion::factory()->for($case, 'testCase')->create();
    }
}
