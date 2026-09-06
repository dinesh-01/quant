<?php

namespace App\IssueTrackers;

/**
 * One issue as the remote host last described it.
 */
final readonly class RemoteIssue
{
    public function __construct(
        public string $id,
        public ?string $url,
        public ?string $status,
        public ?string $summary,
    ) {}
}
