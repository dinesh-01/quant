<?php

namespace App\Enums;

/**
 * The authoring state of a test case version.
 *
 * This is a free-form label rather than a workflow: any status may be set from
 * any other by a user who may manage test cases, matching legacy behaviour
 * (open question Q18). Add a transition table only if that decision changes.
 */
enum TestCaseStatus: string
{
    case Draft = 'draft';
    case ReadyForReview = 'ready_for_review';
    case ReviewInProgress = 'review_in_progress';
    case Rework = 'rework';
    case Obsolete = 'obsolete';
    case Future = 'future';
    case Final = 'final';
}
