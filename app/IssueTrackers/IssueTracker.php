<?php

namespace App\IssueTrackers;

/**
 * A remote issue host. Adapters talk over Laravel's HTTP client.
 */
interface IssueTracker
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array;

    public function createIssue(string $summary, string $description): RemoteIssue;

    public function fetchIssue(string $issueId): ?RemoteIssue;
}
