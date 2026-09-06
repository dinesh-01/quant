<?php

namespace Tests\Feature\Requirements;

use App\Actions\Requirements\CreateRequirement;
use App\Actions\Requirements\CreateRequirementSpec;
use App\Actions\Requirements\CreateRequirementVersion;
use App\Actions\Requirements\FreezeRequirementVersion;
use App\Actions\Requirements\LinkRequirementCoverage;
use App\Actions\Requirements\UnfreezeRequirementVersion;
use App\Actions\Requirements\UpdateRequirementVersion;
use App\Actions\TestSpecification\CreateTestCaseVersion;
use App\Enums\Ability;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use App\Models\Requirement;
use App\Models\RequirementCoverage;
use App\Models\RequirementSpec;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class RequirementLifecycleTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_a_spec_and_requirement_can_be_created(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageRequirements);

        $spec = app(CreateRequirementSpec::class)($user, $project, null, [
            'name' => 'Login',
            'doc_id' => 'SPEC-LOGIN',
        ]);

        $requirement = app(CreateRequirement::class)($user, $spec, [
            'name' => 'Valid credentials',
            'doc_id' => 'REQ-LOGIN-1',
            'scope' => '<p>A known user can sign in.</p>',
            'status' => RequirementStatus::Draft,
            'type' => RequirementType::Feature,
            'expected_coverage' => 1,
        ]);

        $this->assertSame('SPEC-LOGIN', $spec->doc_id);
        $this->assertSame('REQ-LOGIN-1', $requirement->doc_id);
        $this->assertSame(1, $requirement->latestVersion->version);
        $this->assertTrue($requirement->latestVersion->is_open);
    }

    public function test_document_ids_are_unique_per_project(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageRequirements);
        $spec = app(CreateRequirementSpec::class)($user, $project, null, [
            'name' => 'Login',
            'doc_id' => 'SPEC-LOGIN',
        ]);

        try {
            app(CreateRequirementSpec::class)($user, $project, null, [
                'name' => 'Other',
                'doc_id' => 'SPEC-LOGIN',
            ]);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['That document id is already used in this project.'],
                $exception->errors()['doc_id'],
            );
        }

        app(CreateRequirement::class)($user, $spec, [
            'name' => 'One',
            'doc_id' => 'REQ-1',
            'status' => RequirementStatus::Draft,
            'type' => RequirementType::Feature,
            'expected_coverage' => 1,
        ]);

        try {
            app(CreateRequirement::class)($user, $spec, [
                'name' => 'Two',
                'doc_id' => 'REQ-1',
                'status' => RequirementStatus::Draft,
                'type' => RequirementType::Feature,
                'expected_coverage' => 1,
            ]);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['That document id is already used in this project.'],
                $exception->errors()['doc_id'],
            );
        }
    }

    public function test_a_new_requirement_version_does_not_take_coverage_with_it(): void
    {
        [$project, $user, $requirement, $caseVersion] = $this->linkedPair();

        app(CreateRequirementVersion::class)($user, $requirement);

        $this->assertSame(1, RequirementCoverage::query()->count());
        $this->assertSame(
            $requirement->versions()->where('version', 1)->value('id'),
            RequirementCoverage::query()->value('requirement_version_id'),
        );
        $this->assertSame(0, $requirement->fresh()->latestVersion->coverages()->count());
        $this->assertSame($caseVersion->id, RequirementCoverage::query()->value('test_case_version_id'));
    }

    public function test_a_new_test_case_version_does_not_take_coverage_with_it(): void
    {
        [$project, $user, $requirement, $caseVersion] = $this->linkedPair(withCaseManage: true);

        app(CreateTestCaseVersion::class)($user, $caseVersion->testCase);

        $this->assertSame(1, RequirementCoverage::query()->count());
        $this->assertSame($caseVersion->id, RequirementCoverage::query()->value('test_case_version_id'));
        $this->assertSame(0, $caseVersion->testCase->fresh()->latestVersion->requirementCoverages()->count());
    }

    public function test_a_frozen_requirement_version_cannot_be_edited(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageRequirements);
        $spec = RequirementSpec::factory()->for($project)->create();
        $requirement = Requirement::factory()->for($spec, 'requirementSpec')->withVersion()->create();

        app(FreezeRequirementVersion::class)($user, $requirement->latestVersion);

        try {
            app(UpdateRequirementVersion::class)($user, $requirement->latestVersion->fresh(), [
                'scope' => 'changed',
                'status' => RequirementStatus::Valid,
                'type' => RequirementType::Feature,
                'expected_coverage' => 1,
            ]);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('version', $exception->errors());
        }
    }

    public function test_reopening_a_requirement_needs_the_unfreeze_ability(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageRequirements);
        $spec = RequirementSpec::factory()->for($project)->create();
        $requirement = Requirement::factory()->for($spec, 'requirementSpec')->withVersion()->create();
        $version = app(FreezeRequirementVersion::class)($user, $requirement->latestVersion);

        $this->expectException(AuthorizationException::class);

        app(UnfreezeRequirementVersion::class)($user, $version);
    }

    public function test_coverage_cannot_cross_projects(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageRequirementCoverage, Ability::ManageRequirements);
        $spec = RequirementSpec::factory()->for($project)->create();
        $requirement = Requirement::factory()->for($spec, 'requirementSpec')->withVersion()->create();
        $other = TestCaseModel::factory()->withVersion()->create();

        try {
            app(LinkRequirementCoverage::class)($user, $requirement->latestVersion, $other->latestVersion);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Coverage can only link versions in the same project.'],
                $exception->errors()['test_case_version_id'],
            );
        }
    }

    /**
     * @return array{0: TestProject, 1: User, 2: Requirement, 3: TestCaseVersion}
     */
    private function linkedPair(bool $withCaseManage = false): array
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan(
            $project,
            Ability::ManageRequirements,
            Ability::ManageRequirementCoverage,
            ...($withCaseManage ? [Ability::ManageTestCases] : []),
        );
        $spec = RequirementSpec::factory()->for($project)->create();
        $requirement = Requirement::factory()->for($spec, 'requirementSpec')->withVersion()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($suite, 'testSuite')->withVersion()->create();

        app(LinkRequirementCoverage::class)($user, $requirement->latestVersion, $case->latestVersion);

        return [$project, $user, $requirement->fresh(), $case->latestVersion];
    }
}
