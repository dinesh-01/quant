<?php

namespace App\Actions\Requirements;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use App\Models\Requirement;
use App\Models\RequirementSpec;
use App\Models\RequirementVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates a requirement together with its first version.
 */
final class CreateRequirement
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, doc_id: string, sort_order?: int, scope?: string|null, status?: RequirementStatus, type?: RequirementType, expected_coverage?: int}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, RequirementSpec $spec, array $attributes): Requirement
    {
        $spec->loadMissing('testProject');
        $project = $spec->testProject;

        Gate::forUser($user)->authorize(Ability::ManageRequirements->value, $project);

        $taken = Requirement::query()
            ->where('test_project_id', $project->getKey())
            ->where('doc_id', $attributes['doc_id'])
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'doc_id' => 'That document id is already used in this project.',
            ]);
        }

        return DB::transaction(function () use ($user, $spec, $project, $attributes): Requirement {
            $requirement = new Requirement;
            $requirement->fill($attributes);
            $requirement->test_project_id = $project->getKey();
            $requirement->requirement_spec_id = $spec->getKey();
            $requirement->sort_order = $attributes['sort_order'] ?? $this->nextSortOrder($spec);
            $requirement->save();

            $version = new RequirementVersion;
            $version->fill($attributes);
            $version->requirement_id = $requirement->getKey();
            $version->version = 1;
            $version->is_open = true;
            $version->author_id = $user->getKey();
            $version->save();

            $requirement->setRelation('latestVersion', $version);

            $this->audit->record(AuditAction::RequirementCreated, $user, $requirement, [
                'name' => $requirement->name,
                'doc_id' => $requirement->doc_id,
                'requirement_spec_id' => $requirement->requirement_spec_id,
            ]);

            return $requirement;
        });
    }

    private function nextSortOrder(RequirementSpec $spec): int
    {
        return (int) Requirement::query()
            ->where('requirement_spec_id', $spec->getKey())
            ->max('sort_order') + 1;
    }
}
