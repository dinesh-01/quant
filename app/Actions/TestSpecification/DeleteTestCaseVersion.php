<?php

namespace App\Actions\TestSpecification;

use App\Actions\Attachments\PurgeAttachments;
use App\Actions\Audit\AuditLogger;
use App\Actions\CustomFields\PurgeCustomFieldValues;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestCaseVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a single version of a test case.
 *
 * Deleting a frozen version needs `delete_frozen_test_case_versions` on top of
 * `manage_test_cases`, which is the whole reason that ability exists: a frozen
 * version is what an execution record refers to.
 *
 * The last remaining version cannot be deleted, because a case with no version
 * has no content, no status and nothing to render. Delete the case instead.
 * Version numbers are never renumbered after a delete, so a case may legally
 * have versions 1 and 3, and the next new version follows the highest.
 */
final class DeleteTestCaseVersion
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PurgeAttachments $purgeAttachments,
        private readonly PurgeCustomFieldValues $purgeCustomFieldValues,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException when this is the case's only version
     */
    public function __invoke(User $user, TestCaseVersion $version): void
    {
        $version->loadMissing('testCase.testProject');
        $case = $version->testCase;

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $case->testProject);

        if ($version->isFrozen()) {
            Gate::forUser($user)->authorize(
                Ability::DeleteFrozenTestCaseVersions->value,
                $case->testProject,
            );
        }

        if ($case->versions()->count() === 1) {
            throw ValidationException::withMessages([
                'version' => 'A test case must keep at least one version. Delete the test case instead.',
            ]);
        }

        DB::transaction(function () use ($user, $case, $version): void {
            /* No foreign key reaches a polymorphic link, so this is the cascade. */
            $attachments = $this->purgeAttachments->forVersion($version);
            $customFieldValues = $this->purgeCustomFieldValues->forVersion($version);

            $this->audit->record(AuditAction::TestCaseVersionDeleted, $user, $case, [
                'name' => $case->name,
                'version' => $version->version,
                'was_frozen' => $version->isFrozen(),
                'attachments' => $attachments,
                'custom_field_values' => $customFieldValues,
            ]);

            $version->delete();
        });
    }
}
