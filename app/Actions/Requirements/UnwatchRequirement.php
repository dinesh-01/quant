<?php

namespace App\Actions\Requirements;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Requirement;
use App\Models\RequirementMonitor;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Stops watching a requirement. The actor may drop their own watch without
 * a second ability; removing someone else's needs monitor_requirements.
 */
final class UnwatchRequirement
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, RequirementMonitor $monitor): void
    {
        $monitor->loadMissing('requirement.testProject');

        if ($monitor->user_id !== $user->getKey()) {
            Gate::forUser($user)->authorize(
                Ability::MonitorRequirements->value,
                $monitor->requirement->testProject,
            );
        } else {
            Gate::forUser($user)->authorize(
                Ability::ViewRequirements->value,
                $monitor->requirement->testProject,
            );
        }

        $this->audit->record(AuditAction::RequirementUnwatched, $user, $monitor->requirement, [
            'requirement_id' => $monitor->requirement_id,
            'was_own' => $monitor->user_id === $user->getKey(),
        ]);

        $monitor->delete();
    }
}
