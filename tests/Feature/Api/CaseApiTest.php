<?php

namespace Tests\Feature\Api;

use App\Enums\Ability;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseScriptLink;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class CaseApiTest extends TestCase
{
    use AuthenticatesApiTokens;
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_lists_project_cases_with_a_cursor(): void
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        TestCaseModel::factory()->for($suite, 'testSuite')->withVersion()->count(3)->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $first = $this->apiAs($user)
            ->getJson(route('api.v1.projects.cases.index', $project).'?limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.has_more', true);

        $cursor = $first->json('meta.next_cursor');
        $this->assertIsString($cursor);
        $this->assertNotSame('', $cursor);

        $this->apiAs($user)
            ->getJson(route('api.v1.projects.cases.index', $project).'?limit=2&cursor='.$cursor)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.has_more', false);
    }

    public function test_returns_403_when_listing_cases_without_view(): void
    {
        $project = TestProject::factory()->restricted()->create();
        $user = $this->userWhoCan(TestProject::factory()->create(), Ability::ViewTestCases);

        $this->apiAs($user)
            ->getJson(route('api.v1.projects.cases.index', $project))
            ->assertForbidden();
    }

    public function test_shows_a_case_with_its_latest_version_and_script_links(): void
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($suite, 'testSuite')->create();
        $version = TestCaseVersion::factory()->for($case, 'testCase')->create();
        $link = TestCaseScriptLink::factory()->for($version, 'testCaseVersion')->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->apiAs($user)
            ->getJson(route('api.v1.cases.show', $case))
            ->assertOk()
            ->assertJsonPath('data.full_external_id', $case->fullExternalId())
            ->assertJsonPath('data.latest_version.id', $version->id)
            ->assertJsonPath('data.latest_version.script_links.0.path', $link->path);
    }

    public function test_creates_a_case_and_returns_201(): void
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);

        $this->apiAs($user)
            ->postJson(route('api.v1.cases.store', [$project, $suite]), [
                'name' => 'Login succeeds',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Login succeeds')
            ->assertJsonPath('data.latest_version.version', 1);

        $this->assertDatabaseHas('test_cases', [
            'test_suite_id' => $suite->id,
            'name' => 'Login succeeds',
        ]);
    }

    public function test_returns_403_when_creating_a_case_without_manage(): void
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->apiAs($user)
            ->postJson(route('api.v1.cases.store', [$project, $suite]), [
                'name' => 'Should fail',
            ])
            ->assertForbidden();
    }

    public function test_returns_404_when_the_suite_is_not_in_the_project(): void
    {
        $project = TestProject::factory()->create();
        $foreignSuite = TestSuite::factory()->for(TestProject::factory())->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);

        $this->apiAs($user)
            ->postJson(route('api.v1.cases.store', [$project, $foreignSuite]), [
                'name' => 'Should fail',
            ])
            ->assertNotFound();
    }
}
