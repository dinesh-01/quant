<?php

namespace App\Enums;

/**
 * The kind of repository host a project's code tracker points at.
 *
 * The type decides how "test connection" builds a URL. This only has to
 * reach the host — issue creation lives on the project's issue tracker.
 */
enum CodeTrackerType: string
{
    case Generic = 'generic';
    case Github = 'github';
    case Gitlab = 'gitlab';
    case Bitbucket = 'bitbucket';
}
