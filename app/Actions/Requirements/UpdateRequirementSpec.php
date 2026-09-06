<?php

namespace App\Actions\Requirements;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\RequirementSpec;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits a spec's name, document id or description.
 */
final class UpdateRequirementSpec
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name?: string, doc_id?: string, description?: string|null}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, RequirementSpec $spec, array $attributes): RequirementSpec
    {
        $spec->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageRequirements->value, $spec->testProject);

        if (isset($attributes['doc_id'])) {
            $taken = RequirementSpec::query()
                ->where('test_project_id', $spec->test_project_id)
                ->where('doc_id', $attributes['doc_id'])
                ->whereKeyNot($spec->getKey())
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'doc_id' => 'That document id is already used in this project.',
                ]);
            }
        }

        $spec->fill($attributes);
        $properties = $this->audit->changes($spec);
        $spec->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::RequirementSpecUpdated, $user, $spec, $properties);
        }

        return $spec;
    }
}
