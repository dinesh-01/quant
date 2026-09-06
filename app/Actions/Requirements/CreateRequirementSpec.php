<?php

namespace App\Actions\Requirements;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\RequirementSpec;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Adds a requirement spec at the project root or inside another spec.
 */
final class CreateRequirementSpec
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, doc_id: string, description?: string|null, sort_order?: int}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, TestProject $project, ?RequirementSpec $parent, array $attributes): RequirementSpec
    {
        Gate::forUser($user)->authorize(Ability::ManageRequirements->value, $project);

        $this->assertUniqueDocId($project, $attributes['doc_id']);

        if ($parent !== null) {
            if ($parent->test_project_id !== $project->getKey()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'The parent specification belongs to a different test project.',
                ]);
            }

            if ($parent->depth() >= RequirementSpec::MAX_DEPTH) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Requirement specs cannot be nested more than '.RequirementSpec::MAX_DEPTH.' levels deep.',
                ]);
            }
        }

        $spec = new RequirementSpec;
        $spec->fill($attributes);
        $spec->test_project_id = $project->getKey();
        $spec->parent_id = $parent?->getKey();
        $spec->sort_order = $attributes['sort_order'] ?? $this->nextSortOrder($project, $parent);

        $properties = $this->audit->changes($spec);

        $spec->save();

        $this->audit->record(AuditAction::RequirementSpecCreated, $user, $spec, $properties);

        return $spec;
    }

    private function assertUniqueDocId(TestProject $project, string $docId): void
    {
        $taken = RequirementSpec::query()
            ->where('test_project_id', $project->getKey())
            ->where('doc_id', $docId)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'doc_id' => 'That document id is already used in this project.',
            ]);
        }
    }

    private function nextSortOrder(TestProject $project, ?RequirementSpec $parent): int
    {
        return (int) RequirementSpec::query()
            ->where('test_project_id', $project->getKey())
            ->where('parent_id', $parent?->getKey())
            ->max('sort_order') + 1;
    }
}
