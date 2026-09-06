<?php

namespace App\Actions\Requirements;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Requirement;
use App\Models\RequirementSpec;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Moves a requirement into another spec in the same project.
 */
final class MoveRequirement
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, Requirement $requirement, RequirementSpec $spec): Requirement
    {
        $requirement->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageRequirements->value, $requirement->testProject);

        if ($spec->test_project_id !== $requirement->test_project_id) {
            throw ValidationException::withMessages([
                'requirement_spec_id' => 'A requirement cannot be moved to a different test project.',
            ]);
        }

        $from = $requirement->requirement_spec_id;

        $requirement->requirement_spec_id = $spec->getKey();
        $requirement->sort_order = (int) Requirement::query()
            ->where('requirement_spec_id', $spec->getKey())
            ->whereKeyNot($requirement->getKey())
            ->max('sort_order') + 1;
        $requirement->save();

        $this->audit->record(AuditAction::RequirementMoved, $user, $requirement, [
            'name' => $requirement->name,
            'from_spec_id' => $from,
            'to_spec_id' => $requirement->requirement_spec_id,
        ]);

        return $requirement;
    }
}
