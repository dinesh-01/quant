<?php

namespace App\Enums;

/**
 * The kind of issue host a project's tracker points at.
 *
 * Jira Cloud REST v3 is the only adapter. Direct-database and SOAP hosts
 * from the legacy tool are not carried over.
 */
enum IssueTrackerType: string
{
    case Jira = 'jira';
}
