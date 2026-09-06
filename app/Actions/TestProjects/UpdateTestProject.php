<?php

namespace App\Actions\TestProjects;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits a test project.
 *
 * The prefix is frozen once the project has handed out its first `PREFIX-N`
 * number. Those identifiers are quoted in bug reports and documents outside
 * this application, and renaming the prefix would silently invalidate every
 * one of them with no way to look the old form up. Legacy allowed the rename at
 * any time.
 *
 * The check is on `test_case_counter` rather than on whether any case still
 * exists, because deleting the cases does not un-quote the ids.
 */
final class UpdateTestProject
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, prefix: string, description: string|null, is_active: bool, is_public: bool}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException when the prefix would change after ids were issued
     */
    public function __invoke(User $user, TestProject $project, array $attributes): TestProject
    {
        Gate::forUser($user)->authorize(Ability::ManageTestProjects->value);

        if ($attributes['prefix'] !== $project->prefix && $project->test_case_counter > 0) {
            throw ValidationException::withMessages([
                'prefix' => "The prefix cannot be changed once test cases have been numbered. {$project->prefix}-1 onwards are already in use outside this application.",
            ]);
        }

        $project->fill($attributes);

        $properties = $this->audit->changes($project);

        $project->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::TestProjectUpdated, $user, $project, $properties);
        }

        return $project;
    }
}
