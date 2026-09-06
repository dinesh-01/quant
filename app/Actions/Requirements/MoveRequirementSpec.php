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
 * Re-parents a spec within its project, carrying its subtree.
 */
final class MoveRequirementSpec
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, RequirementSpec $spec, ?RequirementSpec $newParent): RequirementSpec
    {
        $spec->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageRequirements->value, $spec->testProject);

        if ($newParent !== null) {
            $this->assertTargetIsUsable($spec, $newParent);
        }

        $from = $spec->parent_id;

        $spec->parent_id = $newParent?->getKey();
        $spec->sort_order = $this->nextSortOrder($spec, $newParent);
        $spec->save();

        $this->audit->record(AuditAction::RequirementSpecMoved, $user, $spec, [
            'name' => $spec->name,
            'from_parent_id' => $from,
            'to_parent_id' => $spec->parent_id,
        ]);

        return $spec;
    }

    /**
     * @throws ValidationException
     */
    private function assertTargetIsUsable(RequirementSpec $spec, RequirementSpec $newParent): void
    {
        if ($newParent->test_project_id !== $spec->test_project_id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A specification cannot be moved to a different test project.',
            ]);
        }

        if ($newParent->is($spec) || $spec->isAncestorOf($newParent)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A specification cannot be moved into itself or one of its own children.',
            ]);
        }

        if ($newParent->depth() + 1 + $spec->descendantDepth() > RequirementSpec::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'parent_id' => 'That move would nest specifications more than '.RequirementSpec::MAX_DEPTH.' levels deep.',
            ]);
        }
    }

    private function nextSortOrder(RequirementSpec $spec, ?RequirementSpec $newParent): int
    {
        return (int) RequirementSpec::query()
            ->where('test_project_id', $spec->test_project_id)
            ->where('parent_id', $newParent?->getKey())
            ->whereKeyNot($spec->getKey())
            ->max('sort_order') + 1;
    }
}
