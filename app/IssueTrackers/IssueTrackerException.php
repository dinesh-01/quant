<?php

namespace App\IssueTrackers;

use RuntimeException;

/**
 * The remote host refused or could not complete an issue operation.
 */
final class IssueTrackerException extends RuntimeException {}
