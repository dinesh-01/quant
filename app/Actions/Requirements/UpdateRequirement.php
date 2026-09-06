<?php

namespace App\Actions\Requirements;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Renames a requirement or changes its document id.
 */
final class UpdateRequirement
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name?: string, doc_id?: string}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, Requirement $requirement, array $attributes): Requirement
    {
        $requirement->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageRequirements->value, $requirement->testProject);

        if (isset($attributes['doc_id'])) {
            $taken = Requirement::query()
                ->where('test_project_id', $requirement->test_project_id)
                ->where('doc_id', $attributes['doc_id'])
                ->whereKeyNot($requirement->getKey())
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'doc_id' => 'That document id is already used in this project.',
                ]);
            }
        }

        $requirement->fill($attributes);
        $properties = $this->audit->changes($requirement);
        $requirement->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::RequirementUpdated, $user, $requirement, $properties);
        }

        return $requirement;
    }
}
