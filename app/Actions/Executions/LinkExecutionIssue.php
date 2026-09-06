<?php

namespace App\Actions\Executions;

use App\Actions\Audit\AuditLogger;
use App\Actions\IssueTrackers\EnrichExecutionIssue;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Execution;
use App\Models\ExecutionIssue;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Records an external issue id on a run. Does not create the issue.
 *
 * When the project has a usable tracker, the link is enriched with the
 * remote status and browse URL. Create-from-fail is a separate action.
 */
final class LinkExecutionIssue
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EnrichExecutionIssue $enrich,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, Execution $execution, string $issueId): ExecutionIssue
    {
        $execution->loadMissing('testPlan.testProject.issueTracker');

        Gate::forUser($user)->authorize(Ability::ExecuteTests->value, $execution->testPlan);

        $issueId = trim($issueId);

        if ($issueId === '') {
            throw ValidationException::withMessages([
                'issue_id' => 'Enter an issue id.',
            ]);
        }

        $already = $execution->issues()->where('issue_id', $issueId)->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'issue_id' => 'That issue is already linked to this run.',
            ]);
        }

        $issue = new ExecutionIssue;
        $issue->execution_id = $execution->getKey();
        $issue->issue_id = $issueId;
        $issue->save();

        $tracker = $execution->testPlan->testProject->issueTracker;

        if ($tracker !== null && $tracker->isUsable()) {
            ($this->enrich)($issue, $tracker);
        }

        $this->audit->record(AuditAction::ExecutionIssueLinked, $user, $execution->testPlan, [
            'execution_id' => $execution->id,
            'issue_id' => $issueId,
        ]);

        return $issue;
    }
}
