<?php

namespace Tests\Feature\TestSpecification;

use App\Enums\Ability;
use App\Enums\TestCaseImportance;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestCaseEndpointsTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_a_case_is_created_with_its_first_version_and_an_external_id()
    {
        $project = TestProject::factory()->create(['prefix' => 'QA']);
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        $response = $this->actingAs($user)->post(route('test-cases.store', $suite), [
            'name' => 'Login works',
            'summary' => 'Sign in with a valid password',
            'importance' => TestCaseImportance::High->value,
        ]);

        $case = TestCaseModel::query()->sole();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('specification.cases.show', [$project, $case]));

        $this->assertSame(1, $case->external_id);
        $this->assertSame('QA-1', $case->fullExternalId());
        $this->assertSame('Sign in with a valid password', $case->latestVersion->summary);
        $this->assertSame(TestCaseImportance::High, $case->latestVersion->importance);
        $this->assertSame($user->id, $case->latestVersion->author_id);
    }

    public function test_an_unknown_status_is_rejected()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('test-cases.store', $suite), ['name' => 'Nope', 'status' => 'not_a_status'])
            ->assertSessionHasErrors('status');
    }

    public function test_creating_a_case_requires_managing_test_cases()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('test-cases.store', $suite), ['name' => 'Nope'])
            ->assertForbidden();

        $this->assertSame(0, TestCaseModel::query()->count());
    }

    public function test_two_cases_in_one_suite_may_share_a_name()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();

        $this->actingAs($user)->post(route('test-cases.store', $suite), ['name' => 'Same'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('test-cases.store', $suite), ['name' => 'Same'])->assertSessionHasNoErrors();

        $this->assertSame([1, 2], TestCaseModel::query()->orderBy('external_id')->pluck('external_id')->all());
    }

    public function test_a_case_can_be_renamed()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $case = TestCaseModel::factory()->withVersion()->create(['test_project_id' => $project->id]);

        $this->actingAs($user)
            ->put(route('test-cases.update', $case), ['name' => 'Renamed'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $case->refresh()->name);
    }

    public function test_a_case_with_only_open_versions_can_be_deleted()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($suite, 'testSuite')->withVersion()->create();

        $this->actingAs($user)
            ->delete(route('test-cases.destroy', $case))
            ->assertRedirect(route('specification.suites.show', [$project, $suite]));

        $this->assertSame(0, TestCaseModel::query()->count());
    }

    public function test_deleting_a_case_holding_a_frozen_version_needs_the_frozen_ability()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $case = TestCaseModel::factory()->create(['test_project_id' => $project->id]);
        TestCaseVersion::factory()->for($case, 'testCase')->version(1)->frozen()->create();

        $this->actingAs($user)
            ->delete(route('test-cases.destroy', $case))
            ->assertForbidden();

        $this->assertSame(1, TestCaseModel::query()->count());
    }

    public function test_the_frozen_ability_allows_deleting_a_case_holding_a_frozen_version()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan(
            $project,
            Ability::ManageTestCases,
            Ability::DeleteFrozenTestCaseVersions,
        );
        $case = TestCaseModel::factory()->create(['test_project_id' => $project->id]);
        TestCaseVersion::factory()->for($case, 'testCase')->version(1)->frozen()->create();

        $this->actingAs($user)->delete(route('test-cases.destroy', $case));

        $this->assertSame(0, TestCaseModel::query()->count());
    }

    public function test_a_case_can_be_moved_to_another_suite_and_keeps_its_external_id()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $origin = TestSuite::factory()->for($project)->create();
        $target = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($origin, 'testSuite')->withVersion()->create();

        $this->actingAs($user)
            ->post(route('test-cases.move', $case), ['test_suite_id' => $target->id])
            ->assertSessionHasNoErrors();

        $case->refresh();

        $this->assertSame($target->id, $case->test_suite_id);
        $this->assertSame(1, $case->external_id);
    }

    public function test_a_case_cannot_be_moved_to_a_suite_in_another_project()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $case = TestCaseModel::factory()->withVersion()->create(['test_project_id' => $project->id]);
        $foreign = TestSuite::factory()->create();

        $this->actingAs($user)
            ->post(route('test-cases.move', $case), ['test_suite_id' => $foreign->id])
            ->assertSessionHasErrors('test_suite_id');
    }

    public function test_a_case_can_be_copied_into_another_project()
    {
        $source = TestProject::factory()->create();
        $destination = TestProject::factory()->create();
        $user = $this->userWhoCan($source, Ability::ViewTestCases);
        $this->assignProjectRole($user, $destination, Ability::ManageTestCases);
        $case = TestCaseModel::factory()->withVersion()->create(['test_project_id' => $source->id]);
        $target = TestSuite::factory()->for($destination)->create();

        $this->actingAs($user)
            ->post(route('test-cases.copy', $case), ['test_suite_id' => $target->id])
            ->assertSessionHasNoErrors();

        $copy = TestCaseModel::query()->where('test_project_id', $destination->id)->sole();

        $this->assertSame(1, $copy->external_id);
        $this->assertSame($case->name, $copy->name);
    }

    public function test_copying_needs_manage_in_the_target_project()
    {
        $source = TestProject::factory()->create();
        $destination = TestProject::factory()->create();
        $user = $this->userWhoCan($source, Ability::ViewTestCases, Ability::ManageTestCases);
        $this->assignProjectRole($user, $destination, Ability::ViewTestCases);
        $case = TestCaseModel::factory()->withVersion()->create(['test_project_id' => $source->id]);
        $target = TestSuite::factory()->for($destination)->create();

        $this->actingAs($user)
            ->post(route('test-cases.copy', $case), ['test_suite_id' => $target->id])
            ->assertForbidden();

        $this->assertSame(1, TestCaseModel::query()->count());
    }

    public function test_cases_in_a_suite_can_be_reordered()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $first = TestCaseModel::factory()->for($suite, 'testSuite')->create(['sort_order' => 1]);
        $second = TestCaseModel::factory()->for($suite, 'testSuite')->create(['sort_order' => 2]);

        $this->actingAs($user)
            ->post(route('test-cases.reorder', $suite), ['order' => [$second->id, $first->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $second->refresh()->sort_order);
        $this->assertSame(2, $first->refresh()->sort_order);
    }

    public function test_reordering_rejects_a_case_from_another_suite()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $other = TestSuite::factory()->for($project)->create();
        $mine = TestCaseModel::factory()->for($suite, 'testSuite')->create();
        $theirs = TestCaseModel::factory()->for($other, 'testSuite')->create();

        $this->actingAs($user)
            ->post(route('test-cases.reorder', $suite), ['order' => [$mine->id, $theirs->id]])
            ->assertSessionHasErrors('order.1');
    }
}
