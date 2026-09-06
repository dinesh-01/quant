<?php

namespace App\Enums;

/**
 * Whether a test case version or an individual step is run by a person or by
 * an automated suite.
 */
enum TestCaseExecutionType: string
{
    case Manual = 'manual';
    case Automated = 'automated';
}
