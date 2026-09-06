<?php

namespace App\Actions\Requirements;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use App\Models\RequirementVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits an open requirement version. A frozen version is refused.
 */
final class UpdateRequirementVersion
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotifyRequirementWatchers $notifyWatchers,
    ) {}

    /**
     * @param  array{scope?: string|null, status?: RequirementStatus, type?: RequirementType, expected_coverage?: int}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, RequirementVersion $version, array $attributes): RequirementVersion
    {
        $version->loadMissing('requirement.testProject');

        Gate::forUser($user)->authorize(Ability::ManageRequirements->value, $version->requirement->testProject);

        if ($version->isFrozen()) {
            throw ValidationException::withMessages([
                'version' => 'This requirement version is frozen. Reopen it or create a new version to make changes.',
            ]);
        }

        $version->fill($attributes);
        $version->updater_id = $user->getKey();

        $properties = $this->audit->changes($version);
        unset($properties['updater_id']);

        $version->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::RequirementVersionUpdated, $user, $version->requirement, [
                'version' => $version->version,
                ...$properties,
            ]);

            ($this->notifyWatchers)($version->requirement, $user, 'updated version '.$version->version);
        }

        return $version;
    }
}
