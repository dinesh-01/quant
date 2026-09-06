<?php

namespace App\Actions\Requirements;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\RequirementSpec;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Deletes a spec and everything nested beneath it via foreign keys.
 */
final class DeleteRequirementSpec
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, RequirementSpec $spec): void
    {
        $spec->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageRequirements->value, $spec->testProject);

        $this->audit->record(AuditAction::RequirementSpecDeleted, $user, $spec, [
            'name' => $spec->name,
            'doc_id' => $spec->doc_id,
            'parent_id' => $spec->parent_id,
            'requirements' => $spec->requirements()->count(),
        ]);

        $spec->delete();
    }
}
