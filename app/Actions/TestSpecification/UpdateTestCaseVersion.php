<?php

namespace App\Actions\TestSpecification;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\TestCaseExecutionType;
use App\Enums\TestCaseImportance;
use App\Enums\TestCaseStatus;
use App\Models\TestCaseVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits the content of an open test case version.
 *
 * A frozen version is refused outright. Freezing exists so that an execution
 * keeps describing what was actually run, and editing in place would silently
 * rewrite history for every execution already recorded against it. Reopen the
 * version or create a new one instead.
 */
final class UpdateTestCaseVersion
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{summary?: string|null, preconditions?: string|null, status?: TestCaseStatus, importance?: TestCaseImportance, execution_type?: TestCaseExecutionType, estimated_duration?: string|null}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException when the version is frozen
     */
    public function __invoke(User $user, TestCaseVersion $version, array $attributes): TestCaseVersion
    {
        $version->loadMissing('testCase.testProject');

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $version->testCase->testProject);

        if ($version->isFrozen()) {
            throw ValidationException::withMessages([
                'version' => 'This test case version is frozen. Reopen it or create a new version to make changes.',
            ]);
        }

        $version->fill($attributes);
        $version->updater_id = $user->getKey();

        /**
         * `updater_id` on the row only ever names the *last* editor, so it
         * cannot answer who made a particular change. This is what gives the
         * sequence.
         */
        $properties = $this->audit->changes($version);
        unset($properties['updater_id']);

        $version->save();

        if ($properties !== []) {
            /**
             * The subject is the case, not the version: a version has no name,
             * so pointing the log at one would show a bare id. The version
             * number goes in the properties instead.
             */
            $this->audit->record(AuditAction::TestCaseVersionUpdated, $user, $version->testCase, [
                'version' => $version->version,
                ...$properties,
            ]);
        }

        return $version;
    }
}
