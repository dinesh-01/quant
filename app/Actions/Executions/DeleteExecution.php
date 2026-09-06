<?php

namespace App\Actions\Executions;

use App\Actions\Attachments\PurgeAttachments;
use App\Actions\Audit\AuditLogger;
use App\Actions\CustomFields\PurgeCustomFieldValues;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Execution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Removes a run. Drafts of your own can go with execute_tests;
 * anyone else's completed run needs delete_executions.
 */
final class DeleteExecution
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PurgeAttachments $purgeAttachments,
        private readonly PurgeCustomFieldValues $purgeCustomFieldValues,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, Execution $execution): void
    {
        $execution->loadMissing('testPlan');

        $ownDraft = $execution->is_draft && $execution->tester_id === $user->getKey();

        if ($ownDraft) {
            Gate::forUser($user)->authorize(Ability::ExecuteTests->value, $execution->testPlan);
        } else {
            Gate::forUser($user)->authorize(Ability::DeleteExecutions->value, $execution->testPlan);
        }

        $attachments = $this->purgeAttachments->forExecution($execution);
        $customFieldValues = $this->purgeCustomFieldValues->forExecution($execution);

        $this->audit->record(AuditAction::ExecutionDeleted, $user, $execution->testPlan, [
            'execution_id' => $execution->id,
            'item_id' => $execution->test_plan_item_id,
            'build_id' => $execution->build_id,
            'status' => $execution->status->value,
            'was_draft' => $execution->is_draft,
            'attachments' => $attachments,
            'custom_field_values' => $customFieldValues,
        ]);

        $execution->delete();
    }
}
