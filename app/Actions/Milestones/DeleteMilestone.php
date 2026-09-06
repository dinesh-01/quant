<?php

namespace App\Actions\Milestones;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Milestone;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Removes a plan milestone. Targets only — no execution history hangs off it.
 */
final class DeleteMilestone
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, Milestone $milestone): void
    {
        $milestone->loadMissing('testPlan');

        Gate::forUser($user)->authorize(Ability::ManageMilestones->value, $milestone->testPlan);

        $this->audit->record(AuditAction::MilestoneDeleted, $user, $milestone->testPlan, [
            'milestone_id' => $milestone->id,
            'name' => $milestone->name,
        ]);

        $milestone->delete();
    }
}
