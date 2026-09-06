<?php

namespace App\Actions\TestSpecification;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestCaseVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Closes a test case version to further editing.
 *
 * Freezing is what lets an execution keep describing what was actually run, so
 * it is gated behind its own ability rather than plain test case management.
 * Freezing an already frozen version is a no-op, not an error.
 */
final class FreezeTestCaseVersion
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestCaseVersion $version): TestCaseVersion
    {
        $version->loadMissing('testCase.testProject');

        Gate::forUser($user)->authorize(Ability::FreezeTestCases->value, $version->testCase->testProject);

        $wasOpen = $version->is_open;

        $version->is_open = false;
        $version->updater_id = $user->getKey();
        $version->save();

        /** Freezing an already frozen version is a no-op, so it is not an act to record. */
        if ($wasOpen) {
            $this->audit->record(AuditAction::TestCaseVersionFrozen, $user, $version->testCase, [
                'version' => $version->version,
            ]);
        }

        return $version;
    }
}
