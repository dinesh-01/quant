<?php

namespace Tests\Feature\TestSpecification;

use App\Enums\Ability;
use App\Enums\TestCaseExecutionType;
use App\Enums\TestCaseImportance;
use App\Enums\TestCaseStatus;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestCaseVersionEndpointsTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_a_new_version_can_be_opened()
    {
        [$project, $user, $case] = $this->caseFor(Ability::ManageTestCases);

        $this->actingAs($user)
            ->post(route('test-case-versions.store', $case))
            ->assertSessionHasNoErrors();

        $this->assertSame([1, 2], $case->refresh()->versions->pluck('version')->all());
    }

    public function test_opening_a_new_version_requires_managing_test_cases()
    {
        [$project, $user, $case] = $this->caseFor(Ability::ViewTestCases);

        $this->actingAs($user)
            ->post(route('test-case-versions.store', $case))
            ->assertForbidden();

        $this->assertSame(1, $case->versions()->count());
    }

    public function test_an_open_version_can_be_edited()
    {
        [$project, $user, $case] = $this->caseFor(Ability::ManageTestCases);
        $version = $case->latestVersion;

        $this->actingAs($user)
            ->put(route('test-case-versions.update', $version), $this->validContent([
                'summary' => 'Rewritten',
                'status' => TestCaseStatus::Final->value,
            ]))
            ->assertSessionHasNoErrors();

        $version->refresh();

        $this->assertSame('Rewritten', $version->summary);
        $this->assertSame(TestCaseStatus::Final, $version->status);
        $this->assertSame($user->id, $version->updater_id);
    }

    public function test_any_status_may_be_set_from_any_other()
    {
        [$project, $user, $case] = $this->caseFor(Ability::ManageTestCases);
        $version = $case->latestVersion;
        $version->status = TestCaseStatus::Final;
        $version->save();

        $this->actingAs($user)
            ->put(route('test-case-versions.update', $version), $this->validContent([
                'status' => TestCaseStatus::Draft->value,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(TestCaseStatus::Draft, $version->refresh()->status);
    }

    public function test_a_frozen_version_cannot_be_edited()
    {
        [$project, $user, $case] = $this->caseFor(Ability::ManageTestCases);
        $version = $case->latestVersion;
        $version->is_open = false;
        $version->save();

        $this->actingAs($user)
            ->put(route('test-case-versions.update', $version), $this->validContent(['summary' => 'Sneaky']))
            ->assertSessionHasErrors('version');

        $this->assertNotSame('Sneaky', $version->refresh()->summary);
    }

    public function test_freezing_requires_its_own_ability()
    {
        [$project, $user, $case] = $this->caseFor(Ability::ManageTestCases);

        $this->actingAs($user)
            ->post(route('test-case-versions.freeze', $case->latestVersion))
            ->assertForbidden();

        $this->assertTrue($case->latestVersion->refresh()->is_open);
    }

    public function test_a_version_can_be_frozen_and_reopened()
    {
        [$project, $user, $case] = $this->caseFor(Ability::ManageTestCases, Ability::FreezeTestCases);
        $version = $case->latestVersion;

        $this->actingAs($user)->post(route('test-case-versions.freeze', $version));

        $this->assertTrue($version->refresh()->isFrozen());

        $this->actingAs($user)->post(route('test-case-versions.unfreeze', $version));

        $this->assertFalse($version->refresh()->isFrozen());
    }

    public function test_a_reopened_version_can_be_edited_again()
    {
        [$project, $user, $case] = $this->caseFor(Ability::ManageTestCases, Ability::FreezeTestCases);
        $version = $case->latestVersion;

        $this->actingAs($user)->post(route('test-case-versions.freeze', $version));
        $this->actingAs($user)->post(route('test-case-versions.unfreeze', $version));

        $this->actingAs($user)
            ->put(route('test-case-versions.update', $version), $this->validContent(['summary' => 'Allowed now']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Allowed now', $version->refresh()->summary);
    }

    public function test_the_only_version_of_a_case_cannot_be_deleted()
    {
        [$project, $user, $case] = $this->caseFor(Ability::ManageTestCases);

        $this->actingAs($user)
            ->delete(route('test-case-versions.destroy', $case->latestVersion))
            ->assertSessionHasErrors('version');

        $this->assertSame(1, $case->versions()->count());
    }

    public function test_an_open_version_can_be_deleted_when_another_remains()
    {
        [$project, $user, $case] = $this->caseFor(Ability::ManageTestCases);
        $second = TestCaseVersion::factory()->for($case, 'testCase')->version(2)->create();

        $this->actingAs($user)
            ->delete(route('test-case-versions.destroy', $second))
            ->assertSessionHasNoErrors();

        $this->assertSame([1], $case->refresh()->versions->pluck('version')->all());
    }

    public function test_deleting_a_frozen_version_requires_the_frozen_ability()
    {
        [$project, $user, $case] = $this->caseFor(Ability::ManageTestCases);
        $frozen = TestCaseVersion::factory()->for($case, 'testCase')->version(2)->frozen()->create();

        $this->actingAs($user)
            ->delete(route('test-case-versions.destroy', $frozen))
            ->assertForbidden();

        $this->assertSame(2, $case->versions()->count());
    }

    public function test_the_frozen_ability_allows_deleting_a_frozen_version()
    {
        [$project, $user, $case] = $this->caseFor(
            Ability::ManageTestCases,
            Ability::DeleteFrozenTestCaseVersions,
        );
        $frozen = TestCaseVersion::factory()->for($case, 'testCase')->version(2)->frozen()->create();

        $this->actingAs($user)->delete(route('test-case-versions.destroy', $frozen));

        $this->assertSame([1], $case->refresh()->versions->pluck('version')->all());
    }

    public function test_version_numbers_are_not_reused_after_a_delete()
    {
        [$project, $user, $case] = $this->caseFor(Ability::ManageTestCases);
        $second = TestCaseVersion::factory()->for($case, 'testCase')->version(2)->create();

        $this->actingAs($user)->delete(route('test-case-versions.destroy', $second));
        $this->actingAs($user)->post(route('test-case-versions.store', $case->refresh()));

        $this->assertSame([1, 2], $case->refresh()->versions->pluck('version')->all());
    }

    /**
     * The update endpoint requires the whole content set, so tests that care
     * about one field still have to send the rest.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validContent(array $overrides = []): array
    {
        return [
            'summary' => 'A summary',
            'preconditions' => null,
            'status' => TestCaseStatus::Draft->value,
            'importance' => TestCaseImportance::Medium->value,
            'execution_type' => TestCaseExecutionType::Manual->value,
            'estimated_duration' => null,
            ...$overrides,
        ];
    }

    /**
     * @return array{TestProject, User, TestCaseModel}
     */
    private function caseFor(Ability ...$abilities): array
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, ...$abilities);
        $case = TestCaseModel::factory()->withVersion()->create(['test_project_id' => $project->id]);

        return [$project, $user, $case];
    }
}
