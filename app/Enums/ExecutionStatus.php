<?php

namespace App\Enums;

/**
 * The result of a test run, or of one step inside it.
 */
enum ExecutionStatus: string
{
    case NotRun = 'not_run';
    case Passed = 'passed';
    case Failed = 'failed';
    case Blocked = 'blocked';
}
