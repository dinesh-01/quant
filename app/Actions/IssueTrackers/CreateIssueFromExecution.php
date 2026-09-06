<?php

namespace App\Actions\IssueTrackers;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\ExecutionStatus;
use App\IssueTrackers\AdapterFactory;
use App\IssueTrackers\IssueTrackerException;
use App\Models\Execution;
use App\Models\ExecutionIssue;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Opens a tracker issue from a completed failed or blocked run and links it.
 *
 * Testers do this, so the gate is `execute_tests` on the plan — not
 * `manage_issue_trackers`.
 */
final class CreateIssueFromExecution
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AdapterFactory $adapters,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, Execution $execution): ExecutionIssue
    {
        $execution->loadMissing([
            'testPlan.testProject.issueTracker',
            'testPlanItem.testCaseVersion.testCase.testProject',
            'build',
        ]);

        Gate::forUser($user)->authorize(Ability::ExecuteTests->value, $execution->testPlan);

        if ($execution->is_draft) {
            throw ValidationException::withMessages([
                'execution' => 'Complete the run before creating an issue.',
            ]);
        }

        if (! in_array($execution->status, [ExecutionStatus::Failed, ExecutionStatus::Blocked], true)) {
            throw ValidationException::withMessages([
                'execution' => 'Create an issue only from a failed or blocked run.',
            ]);
        }

        $tracker = $execution->testPlan->testProject->issueTracker;

        if ($tracker === null || ! $tracker->isUsable()) {
            throw ValidationException::withMessages([
                'tracker' => 'This project has no enabled issue tracker.',
            ]);
        }

        $case = $execution->testPlanItem->testCaseVersion->testCase;
        $summary = sprintf(
            '%s %s is %s',
            $case->fullExternalId(),
            $case->name,
            $execution->status->name,
        );
        $description = implode("\n\n", array_filter([
            $execution->notes,
            'Plan: '.$execution->testPlan->name,
            'Build: '.$execution->build->name,
            'Version: v'.$execution->version,
        ], fn (?string $line): bool => $line !== null && $line !== ''));

        try {
            $remote = $this->adapters->make($tracker)->createIssue($summary, $description);
        } catch (IssueTrackerException $exception) {
            throw ValidationException::withMessages([
                'tracker' => $exception->getMessage(),
            ]);
        }

        $already = $execution->issues()->where('issue_id', $remote->id)->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'issue_id' => 'That issue is already linked to this run.',
            ]);
        }

        $issue = new ExecutionIssue;
        $issue->execution_id = $execution->getKey();
        $issue->issue_id = $remote->id;
        $issue->issue_url = $remote->url;
        $issue->issue_status = $remote->status;
        $issue->issue_summary = $remote->summary;
        $issue->status_fetched_at = now();
        $issue->save();

        $this->audit->record(AuditAction::ExecutionIssueCreated, $user, $execution->testPlan, [
            'execution_id' => $execution->id,
            'issue_id' => $remote->id,
            'tracker' => $tracker->type->value,
        ]);

        return $issue;
    }
}
