<?php

namespace App\Enums;

/**
 * The authoring state of a requirement version.
 *
 * Free-form, like test case status: any value may be set from any other by
 * someone who may manage requirements.
 */
enum RequirementStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Rework = 'rework';
    case Finish = 'finish';
    case Implemented = 'implemented';
    case Valid = 'valid';
    case NotTestable = 'not_testable';
    case Obsolete = 'obsolete';
}
