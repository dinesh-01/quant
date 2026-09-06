<?php

namespace App\Actions\IssueTrackers;

use App\IssueTrackers\AdapterFactory;
use App\Models\ExecutionIssue;
use App\Models\IssueTracker;

/**
 * Copies the latest remote status onto a linked issue.
 *
 * Failures stay silent: a typed-in id must still remain linked when the
 * host is down or the key is unknown there.
 */
final class EnrichExecutionIssue
{
    public function __construct(private readonly AdapterFactory $adapters) {}

    public function __invoke(ExecutionIssue $issue, IssueTracker $tracker): void
    {
        $remote = $this->adapters->make($tracker)->fetchIssue($issue->issue_id);

        if ($remote === null) {
            return;
        }

        $issue->issue_url = $remote->url;
        $issue->issue_status = $remote->status;
        $issue->issue_summary = $remote->summary;
        $issue->status_fetched_at = now();
        $issue->save();
    }
}
