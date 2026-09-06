<?php

namespace App\Actions\Requirements;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\RequirementVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Reopens a frozen requirement version. Gated on unfreeze_requirements.
 */
final class UnfreezeRequirementVersion
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, RequirementVersion $version): RequirementVersion
    {
        $version->loadMissing('requirement.testProject');

        Gate::forUser($user)->authorize(Ability::UnfreezeRequirements->value, $version->requirement->testProject);

        $wasFrozen = ! $version->is_open;

        $version->is_open = true;
        $version->updater_id = $user->getKey();
        $version->save();

        if ($wasFrozen) {
            $this->audit->record(AuditAction::RequirementVersionUnfrozen, $user, $version->requirement, [
                'version' => $version->version,
            ]);
        }

        return $version;
    }
}
