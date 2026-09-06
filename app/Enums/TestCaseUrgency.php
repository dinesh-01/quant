<?php

namespace App\Enums;

/**
 * How urgently a planned test case version should be run.
 *
 * This lives on the plan item, not on the case: the same version can be
 * routine in one plan and urgent in another. Combined later with the
 * version's importance to produce an execution priority. Default is Medium,
 * matching legacy's `$tlCfg->testcase_urgency_default`.
 */
enum TestCaseUrgency: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
