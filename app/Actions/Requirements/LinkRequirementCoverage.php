<?php

namespace App\Actions\Requirements;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\RequirementCoverage;
use App\Models\RequirementVersion;
use App\Models\TestCaseVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Pins a test case version to a requirement version.
 *
 * Both ends must belong to the same project. The link stays on these
 * versions when either side opens a new one.
 */
final class LinkRequirementCoverage
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(
        User $user,
        RequirementVersion $requirementVersion,
        TestCaseVersion $testCaseVersion,
    ): RequirementCoverage {
        $requirementVersion->loadMissing('requirement.testProject');
        $testCaseVersion->loadMissing('testCase.testProject');

        $project = $requirementVersion->requirement->testProject;

        Gate::forUser($user)->authorize(Ability::ManageRequirementCoverage->value, $project);

        if ($testCaseVersion->testCase->test_project_id !== $project->getKey()) {
            throw ValidationException::withMessages([
                'test_case_version_id' => 'Coverage can only link versions in the same project.',
            ]);
        }

        $already = RequirementCoverage::query()
            ->where('requirement_version_id', $requirementVersion->getKey())
            ->where('test_case_version_id', $testCaseVersion->getKey())
            ->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'test_case_version_id' => 'That test case version already covers this requirement version.',
            ]);
        }

        $coverage = new RequirementCoverage;
        $coverage->requirementVersion()->associate($requirementVersion);
        $coverage->testCaseVersion()->associate($testCaseVersion);
        $coverage->author_id = $user->getKey();
        $coverage->save();

        $this->audit->record(AuditAction::RequirementCoverageLinked, $user, $requirementVersion->requirement, [
            'requirement_version' => $requirementVersion->version,
            'test_case_id' => $testCaseVersion->test_case_id,
            'test_case_version' => $testCaseVersion->version,
        ]);

        return $coverage;
    }
}
