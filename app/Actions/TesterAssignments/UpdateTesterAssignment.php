<?php

namespace App\Actions\TesterAssignments;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\TesterAssignmentStatus;
use App\Models\TesterAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Changes the status or deadline of an existing assignment.
 *
 * The tester is not moved: that is a new assignment. Status is the
 * assignment's own lifecycle, not an execution result.
 */
final class UpdateTesterAssignment
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{status?: TesterAssignmentStatus, deadline_at?: Carbon|null}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TesterAssignment $assignment, array $attributes): TesterAssignment
    {
        $assignment->loadMissing('testPlanItem.testPlan');

        Gate::forUser($user)->authorize(Ability::AssignTesters->value, $assignment->testPlanItem->testPlan);

        if (array_key_exists('status', $attributes)) {
            $assignment->status = $attributes['status'];
        }

        if (array_key_exists('deadline_at', $attributes)) {
            $assignment->deadline_at = $attributes['deadline_at'];
        }

        $properties = $this->audit->changes($assignment);

        $assignment->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::TesterAssignmentUpdated, $user, $assignment->testPlanItem->testPlan, [
                'name' => $assignment->testPlanItem->testPlan->name,
                ...$properties,
            ]);
        }

        return $assignment;
    }
}
