<?php

namespace App\Actions\TestProjects;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Creates a test project.
 *
 * `manage_test_projects` is a system ability, so it is checked without a scope
 * and reads from the user's global role only. A project role cannot grant the
 * right to create further projects.
 */
final class CreateTestProject
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, prefix: string, description: string|null, is_active: bool, is_public: bool}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, array $attributes): TestProject
    {
        Gate::forUser($user)->authorize(Ability::ManageTestProjects->value);

        $project = new TestProject;
        $project->fill($attributes);

        $properties = $this->audit->changes($project);

        $project->save();

        $this->audit->record(AuditAction::TestProjectCreated, $user, $project, $properties);

        return $project;
    }
}
