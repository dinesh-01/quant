<?php

namespace App\Actions\TestSpecification;

use App\Actions\Attachments\PurgeAttachments;
use App\Actions\Audit\AuditLogger;
use App\Actions\CustomFields\PurgeCustomFieldValues;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Deletes a suite and everything beneath it.
 *
 * Nested suites, their cases, versions and steps all go with it through the
 * foreign key cascades rather than a hand-written recursion, which is why suite
 * nesting is capped at TestSuite::MAX_DEPTH.
 */
final class DeleteTestSuite
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PurgeAttachments $purgeAttachments,
        private readonly PurgeCustomFieldValues $purgeCustomFieldValues,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestSuite $suite): void
    {
        $suite->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $suite->testProject);

        DB::transaction(function () use ($user, $suite): void {
            /*
             * A polymorphic link carries no foreign key, so the cascade does
             * not reach attachments. Purged first, while the suites and
             * versions they point at can still be found.
             */
            $attachments = $this->purgeAttachments->forSuiteSubtree($suite);
            $customFieldValues = $this->purgeCustomFieldValues->forSuiteSubtree($suite);

            /**
             * Before the delete, with the counts of the subtree the cascade
             * takes with it. The name has to be copied in too: the subject
             * reference will not resolve once the row is gone.
             */
            $this->audit->record(AuditAction::TestSuiteDeleted, $user, $suite, [
                'name' => $suite->name,
                'parent_id' => $suite->parent_id,
                'test_cases' => $suite->testCases()->count(),
                'attachments' => $attachments,
                'custom_field_values' => $customFieldValues,
            ]);

            $suite->delete();
        });
    }
}
