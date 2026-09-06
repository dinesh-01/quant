<?php

namespace App\Actions\IssueTrackers;

use App\Models\ExecutionIssue;
use App\Models\IssueTracker;
use App\Models\TestProject;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Refreshes stale linked-issue statuses when execution history is shown.
 */
final class RefreshIssueStatuses
{
    public const STALE_AFTER_MINUTES = 5;

    public function __construct(private readonly EnrichExecutionIssue $enrich) {}

    /**
     * @param  Collection<int, ExecutionIssue>|iterable<ExecutionIssue>  $issues
     */
    public function __invoke(TestProject $project, iterable $issues): void
    {
        $tracker = $project->issueTracker;

        if (! $tracker instanceof IssueTracker || ! $tracker->isUsable()) {
            return;
        }

        $cutoff = now()->subMinutes(self::STALE_AFTER_MINUTES);

        foreach ($issues as $issue) {
            if (! $issue instanceof ExecutionIssue) {
                continue;
            }

            if ($this->isFresh($issue->status_fetched_at, $cutoff)) {
                continue;
            }

            ($this->enrich)($issue, $tracker);
        }
    }

    private function isFresh(?DateTimeInterface $fetchedAt, DateTimeInterface $cutoff): bool
    {
        return $fetchedAt !== null && $fetchedAt >= $cutoff;
    }
}
