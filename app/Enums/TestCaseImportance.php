<?php

namespace App\Enums;

/**
 * How important a test case version is, which a test plan's urgency is later
 * combined with to produce an execution priority.
 */
enum TestCaseImportance: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
