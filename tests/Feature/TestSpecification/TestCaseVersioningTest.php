<?php

namespace Tests\Feature\TestSpecification;

use App\Actions\TestSpecification\CreateTestCaseVersion;
use App\Actions\TestSpecification\FreezeTestCaseVersion;
use App\Actions\TestSpecification\ReorderTestCaseSteps;
use App\Actions\TestSpecification\UnfreezeTestCaseVersion;
use App\Enums\Ability;
use App\Enums\TestCaseExecutionType;
use App\Enums\TestCaseImportance;
use App\Enums\TestCaseStatus;
use App\Models\Platform;
use App\Models\Role;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TestCaseVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_version_takes_the_next_number()
    {
        [$user, $case] = $this->caseWithVersion();

        $version = app(CreateTestCaseVersion::class)($user, $case);

        $this->assertSame(2, $version->version);
        $this->assertTrue($case->refresh()->latestVersion->is($version));
    }

    public function test_a_new_version_copies_the_content_of_its_source()
    {
        [$user, $case] = $this->caseWithVersion([
            'summary' => 'Sign in with a valid password',
            'preconditions' => 'A registered account exists',
            'importance' => TestCaseImportance::High,
            'execution_type' => TestCaseExecutionType::Automated,
            'estimated_duration' => '12.50',
        ]);

        $version = app(CreateTestCaseVersion::class)($user, $case);

        $this->assertSame('Sign in with a valid password', $version->summary);
        $this->assertSame('A registered account exists', $version->preconditions);
        $this->assertSame(TestCaseImportance::High, $version->importance);
        $this->assertSame(TestCaseExecutionType::Automated, $version->execution_type);
        $this->assertSame('12.50', $version->estimated_duration);
    }

    public function test_a_new_version_inherits_the_status_of_its_source()
    {
        [$user, $case] = $this->caseWithVersion(['status' => TestCaseStatus::Final]);

        $version = app(CreateTestCaseVersion::class)($user, $case);

        $this->assertSame(TestCaseStatus::Final, $version->status);
    }

    public function test_a_new_version_copies_the_steps_of_its_source()
    {
        [$user, $case] = $this->caseWithVersion();
        $source = $case->latestVersion;
        TestCaseStep::factory()->for($source, 'testCaseVersion')->at(1)->create([
            'actions' => 'Open the login page',
            'expected_results' => 'The form is shown',
            'execution_type' => TestCaseExecutionType::Manual,
        ]);
        TestCaseStep::factory()->for($source, 'testCaseVersion')->at(2)->create([
            'actions' => 'Submit valid credentials',
            'expected_results' => 'The dashboard loads',
            'execution_type' => TestCaseExecutionType::Automated,
        ]);

        $version = app(CreateTestCaseVersion::class)($user, $case);

        $steps = $version->steps;

        $this->assertCount(2, $steps);
        $this->assertSame(['Open the login page', 'Submit valid credentials'], $steps->pluck('actions')->all());
        $this->assertSame([1, 2], $steps->pluck('sort_order')->all());
        $this->assertSame(TestCaseExecutionType::Automated, $steps->last()->execution_type);
        $this->assertCount(2, $source->refresh()->steps, 'the source keeps its own steps');
    }

    public function test_a_new_version_copies_the_platform_assignments_of_its_source()
    {
        [$user, $case] = $this->caseWithVersion();
        $source = $case->latestVersion;
        $chrome = Platform::factory()->for($case->testProject)->named('Chrome')->create();
        $safari = Platform::factory()->for($case->testProject)->named('Safari')->create();
        $source->platforms()->attach([$chrome->id, $safari->id]);

        $version = app(CreateTestCaseVersion::class)($user, $case);

        $this->assertSame(
            [$chrome->id, $safari->id],
            $version->platforms()->orderBy('platforms.id')->pluck('platforms.id')->all(),
        );
        $this->assertSame(
            [$chrome->id, $safari->id],
            $source->refresh()->platforms()->orderBy('platforms.id')->pluck('platforms.id')->all(),
            'the source keeps its own assignments',
        );
    }

    public function test_a_new_version_is_open_even_when_branched_from_a_frozen_one()
    {
        [$user, $case] = $this->caseWithVersion(['is_open' => false]);

        $version = app(CreateTestCaseVersion::class)($user, $case);

        $this->assertTrue($version->is_open);
        $this->assertFalse($case->versions()->where('version', 1)->sole()->is_open);
    }

    public function test_a_new_version_records_the_acting_user_and_clears_the_updater()
    {
        $other = User::factory()->create();
        [$user, $case] = $this->caseWithVersion([
            'author_id' => $other->id,
            'updater_id' => $other->id,
        ]);

        $version = app(CreateTestCaseVersion::class)($user, $case);

        $this->assertSame($user->id, $version->author_id);
        $this->assertNull($version->updater_id);
    }

    public function test_a_new_version_leaves_the_external_id_alone()
    {
        [$user, $case] = $this->caseWithVersion();
        $externalId = $case->external_id;

        app(CreateTestCaseVersion::class)($user, $case);

        $this->assertSame($externalId, $case->refresh()->external_id);
    }

    public function test_a_version_may_be_branched_from_an_older_version()
    {
        [$user, $case] = $this->caseWithVersion(['summary' => 'The original']);
        $first = $case->latestVersion;
        TestCaseVersion::factory()->for($case, 'testCase')->version(2)->create(['summary' => 'The newer one']);

        $version = app(CreateTestCaseVersion::class)($user, $case->refresh(), $first);

        $this->assertSame(3, $version->version);
        $this->assertSame('The original', $version->summary);
    }

    public function test_branching_from_a_version_of_another_case_is_rejected()
    {
        [$user, $case] = $this->caseWithVersion();
        $foreign = TestCaseVersion::factory()->create();

        $this->expectException(ValidationException::class);

        app(CreateTestCaseVersion::class)($user, $case, $foreign);
    }

    public function test_a_case_with_no_versions_cannot_be_branched()
    {
        $case = TestCaseModel::factory()->create();
        $user = $this->userWhoCan($case->test_project_id, Ability::ManageTestCases);

        $this->expectException(ValidationException::class);

        app(CreateTestCaseVersion::class)($user, $case);
    }

    public function test_freezing_closes_a_version_and_unfreezing_reopens_it()
    {
        [$user, $case] = $this->caseWithVersion();
        $user = $this->userWhoCan($case->test_project_id, Ability::FreezeTestCases);
        $version = $case->latestVersion;

        app(FreezeTestCaseVersion::class)($user, $version);

        $this->assertTrue($version->refresh()->isFrozen());
        $this->assertSame($user->id, $version->updater_id);

        app(UnfreezeTestCaseVersion::class)($user, $version);

        $this->assertFalse($version->refresh()->isFrozen());
    }

    public function test_freezing_an_already_frozen_version_is_not_an_error()
    {
        [, $case] = $this->caseWithVersion(['is_open' => false]);
        $user = $this->userWhoCan($case->test_project_id, Ability::FreezeTestCases);

        $version = app(FreezeTestCaseVersion::class)($user, $case->latestVersion);

        $this->assertTrue($version->isFrozen());
    }

    public function test_steps_cannot_be_reordered_in_a_frozen_version()
    {
        [$user, $case] = $this->caseWithVersion(['is_open' => false]);
        $version = $case->latestVersion;
        $first = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(1)->create();
        $second = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(2)->create();

        $this->expectException(ValidationException::class);

        (new ReorderTestCaseSteps)($user, $version, [$second->id, $first->id]);
    }

    public function test_reordering_steps_renumbers_them_from_one()
    {
        [$user, $case] = $this->caseWithVersion();
        $version = $case->latestVersion;
        $first = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(4)->create();
        $second = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(9)->create();

        (new ReorderTestCaseSteps)($user, $version, [$second->id, $first->id]);

        $this->assertSame([$second->id, $first->id], $version->steps()->pluck('id')->all());
        $this->assertSame([1, 2], $version->steps()->pluck('sort_order')->all());
    }

    public function test_reordering_steps_requires_every_step_to_be_listed()
    {
        [$user, $case] = $this->caseWithVersion();
        $version = $case->latestVersion;
        $first = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(1)->create();
        TestCaseStep::factory()->for($version, 'testCaseVersion')->at(2)->create();

        $this->expectException(ValidationException::class);

        (new ReorderTestCaseSteps)($user, $version, [$first->id]);
    }

    /**
     * @param  array<string, mixed>  $versionAttributes
     * @return array{User, TestCaseModel}
     */
    private function caseWithVersion(array $versionAttributes = []): array
    {
        $case = TestCaseModel::factory()->create();
        TestCaseVersion::factory()->for($case, 'testCase')->version(1)->create($versionAttributes);

        return [
            $this->userWhoCan($case->test_project_id, Ability::ManageTestCases),
            $case->refresh(),
        ];
    }

    private function userWhoCan(int $projectId, Ability ...$abilities): User
    {
        $user = User::factory()->for(Role::factory()->granting())->create();

        $user->projectRoles()->attach(
            Role::factory()->granting(...$abilities)->create(),
            ['test_project_id' => $projectId],
        );

        return $user;
    }
}
