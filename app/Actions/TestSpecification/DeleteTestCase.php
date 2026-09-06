<?php

namespace App\Actions\TestSpecification;

use App\Actions\Attachments\PurgeAttachments;
use App\Actions\Audit\AuditLogger;
use App\Actions\CustomFields\PurgeCustomFieldValues;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestCase;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Deletes a test case with all of its versions and steps.
 *
 * A case holding any frozen version needs the frozen-version ability as well,
 * for the same reason DeleteTestCaseVersion does: deleting the case is the
 * obvious way around a refusal to delete one frozen version.
 *
 * The freed `external_id` is not reused. The project counter only moves
 * forward, so `PREFIX-N` numbers in old bug reports never come to mean a
 * different case.
 */
final class DeleteTestCase
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PurgeAttachments $purgeAttachments,
        private readonly PurgeCustomFieldValues $purgeCustomFieldValues,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestCase $case): void
    {
        $case->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $case->testProject);

        if ($case->versions()->where('is_open', false)->exists()) {
            Gate::forUser($user)->authorize(
                Ability::DeleteFrozenTestCaseVersions->value,
                $case->testProject,
            );
        }

        DB::transaction(function () use ($user, $case): void {
            /*
             * Attachments hang off each version and have no foreign key to
             * cascade through, so they are removed here while the version ids
             * they name still exist.
             */
            $attachments = $this->purgeAttachments->forTestCase($case);
            $customFieldValues = $this->purgeCustomFieldValues->forTestCase($case);

            $this->audit->record(AuditAction::TestCaseDeleted, $user, $case, [
                'name' => $case->name,
                'external_id' => $case->external_id,
                'test_suite_id' => $case->test_suite_id,
                'versions' => $case->versions()->count(),
                'attachments' => $attachments,
                'custom_field_values' => $customFieldValues,
            ]);

            $case->delete();
        });
    }
}
