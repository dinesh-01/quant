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
 * Reopens a frozen test case version for editing.
 *
 * Gated behind the same ability as freezing, since being able to freeze without
 * being able to reverse it would make the state a trap. Reopening an already
 * open version is a no-op, not an error.
 */
final class UnfreezeTestCaseVersion
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestCaseVersion $version): TestCaseVersion
    {
        $version->loadMissing('testCase.testProject');

        Gate::forUser($user)->authorize(Ability::FreezeTestCases->value, $version->testCase->testProject);

        $wasFrozen = ! $version->is_open;

        $version->is_open = true;
        $version->updater_id = $user->getKey();
        $version->save();

        /**
         * Worth recording precisely because it undoes the protection an
         * execution relies on: after this, the version's content can change
         * under results already recorded against it.
         */
        if ($wasFrozen) {
            $this->audit->record(AuditAction::TestCaseVersionUnfrozen, $user, $version->testCase, [
                'version' => $version->version,
            ]);
        }

        return $version;
    }
}
