<?php

namespace App\Enums;

/**
 * What a bulk keyword run does to the cases it covers.
 *
 * Three named modes rather than a replace, because a bulk replace across a
 * subtree would discard tags the person running it never saw. Legacy offered
 * the same three from one screen.
 */
enum KeywordBulkMode: string
{
    /** Add the chosen keywords, leaving anything else in place. */
    case Assign = 'assign';

    /** Take the chosen keywords off, leaving anything else in place. */
    case Remove = 'remove';

    /** Take every keyword off, whatever it is. */
    case ClearAll = 'clear_all';
}
