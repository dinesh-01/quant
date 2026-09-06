<?php

namespace App\Enums;

/**
 * Where a tester assignment sits in its own lifecycle.
 *
 * This is not an execution result. Open / todo mean the run has not started;
 * completed and closed mean the assignment itself is finished, whether or not
 * a result has been recorded yet. Phase 6 reads this for the "assigned to me"
 * filter; it does not write execution status here.
 */
enum TesterAssignmentStatus: string
{
    case Open = 'open';
    case Todo = 'todo';
    case TodoUrgent = 'todo_urgent';
    case Completed = 'completed';
    case Closed = 'closed';
}
