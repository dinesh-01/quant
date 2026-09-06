<?php

namespace App\Actions\TestSpecification;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestCaseScriptLink;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Removes an automation script from a test case version.
 */
final class UnlinkAutomationScript
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestCaseScriptLink $link): void
    {
        $link->loadMissing('testCaseVersion.testCase.testProject');

        $version = $link->testCaseVersion;

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $version->testCase->testProject);

        $link->delete();

        $this->audit->record(AuditAction::AutomationScriptUnlinked, $user, $version->testCase, [
            'version' => $version->version,
            'repository' => $link->repository,
            'path' => $link->path,
        ]);
    }
}
