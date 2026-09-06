<?php

namespace App\Actions\Executions;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\ExecutionIssue;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Removes an issue id from a run.
 */
final class UnlinkExecutionIssue
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, ExecutionIssue $issue): void
    {
        $issue->loadMissing('execution.testPlan');

        Gate::forUser($user)->authorize(Ability::ExecuteTests->value, $issue->execution->testPlan);

        $this->audit->record(AuditAction::ExecutionIssueUnlinked, $user, $issue->execution->testPlan, [
            'execution_id' => $issue->execution_id,
            'issue_id' => $issue->issue_id,
        ]);

        $issue->delete();
    }
}
