<?php

namespace App\IssueTrackers;

use App\Enums\IssueTrackerType;
use App\Models\IssueTracker as IssueTrackerModel;

/**
 * Builds the adapter for a project's saved tracker.
 */
final class AdapterFactory
{
    public function make(IssueTrackerModel $tracker): IssueTracker
    {
        return match ($tracker->type) {
            IssueTrackerType::Jira => new JiraIssueTracker($tracker),
        };
    }
}
