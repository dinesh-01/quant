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
use Illuminate\Validation\ValidationException;

/**
 * Subscribes the actor to wording changes on one requirement.
 */
final class WatchRequirement
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, Requirement $requirement): RequirementMonitor
    {
        $requirement->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::MonitorRequirements->value, $requirement->testProject);

        $already = RequirementMonitor::query()
            ->where('requirement_id', $requirement->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        if ($already instanceof RequirementMonitor) {
            throw ValidationException::withMessages([
                'requirement' => 'You are already watching this requirement.',
            ]);
        }

        $monitor = new RequirementMonitor;
        $monitor->requirement_id = $requirement->getKey();
        $monitor->user_id = $user->getKey();
        $monitor->save();

        $this->audit->record(AuditAction::RequirementWatched, $user, $requirement, [
            'requirement_id' => $requirement->id,
        ]);

        return $monitor;
    }
}
