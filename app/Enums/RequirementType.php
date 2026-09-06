<?php

namespace App\Enums;

/**
 * What kind of requirement a version describes.
 */
enum RequirementType: string
{
    case Informational = 'informational';
    case Feature = 'feature';
    case UseCase = 'use_case';
    case Interface = 'interface';
    case NonFunctional = 'non_functional';
    case Constraint = 'constraint';
    case SystemFunction = 'system_function';
}
