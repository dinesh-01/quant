<?php

namespace App\Actions\Requirements;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\RequirementCoverage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Removes a coverage link.
 */
final class UnlinkRequirementCoverage
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, RequirementCoverage $coverage): void
    {
        $coverage->loadMissing([
            'requirementVersion.requirement.testProject',
            'testCaseVersion',
        ]);

        $requirement = $coverage->requirementVersion->requirement;

        Gate::forUser($user)->authorize(Ability::ManageRequirementCoverage->value, $requirement->testProject);

        $this->audit->record(AuditAction::RequirementCoverageUnlinked, $user, $requirement, [
            'requirement_version' => $coverage->requirementVersion->version,
            'test_case_id' => $coverage->testCaseVersion->test_case_id,
            'test_case_version' => $coverage->testCaseVersion->version,
        ]);

        $coverage->delete();
    }
}
