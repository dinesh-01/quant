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
 * Closes a requirement version to further editing.
 *
 * Freezing an already frozen version is a no-op.
 */
final class FreezeRequirementVersion
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, RequirementVersion $version): RequirementVersion
    {
        $version->loadMissing('requirement.testProject');

        Gate::forUser($user)->authorize(Ability::ManageRequirements->value, $version->requirement->testProject);

        $wasOpen = $version->is_open;

        $version->is_open = false;
        $version->updater_id = $user->getKey();
        $version->save();

        if ($wasOpen) {
            $this->audit->record(AuditAction::RequirementVersionFrozen, $user, $version->requirement, [
                'version' => $version->version,
            ]);
        }

        return $version;
    }
}
