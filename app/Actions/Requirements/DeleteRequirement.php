<?php

namespace App\Actions\Requirements;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Deletes a requirement and its versions. Coverage rows cascade with them.
 */
final class DeleteRequirement
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotifyRequirementWatchers $notifyWatchers,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, Requirement $requirement): void
    {
        $requirement->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageRequirements->value, $requirement->testProject);

        ($this->notifyWatchers)($requirement, $user, 'deleted the requirement', true);

        $this->audit->record(AuditAction::RequirementDeleted, $user, $requirement, [
            'name' => $requirement->name,
            'doc_id' => $requirement->doc_id,
            'versions' => $requirement->versions()->count(),
        ]);

        $requirement->delete();
    }
}
