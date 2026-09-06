<?php

namespace Tests\Feature\TestSpecification;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestProject;
use App\Models\User;

/**
 * Role setup shared by the HTTP tests in this area.
 *
 * The global role deliberately grants nothing, so every assertion about access
 * is really testing the project role rather than falling back to a permissive
 * global one.
 */
trait InteractsWithSpecificationRoles
{
    protected function userWhoCan(TestProject $project, Ability ...$abilities): User
    {
        $user = User::factory()->for(Role::factory()->granting())->create();

        $this->assignProjectRole($user, $project, ...$abilities);

        return $user;
    }

    protected function assignProjectRole(User $user, TestProject $project, Ability ...$abilities): void
    {
        $user->projectRoles()->attach(
            Role::factory()->granting(...$abilities)->create(),
            ['test_project_id' => $project->id],
        );
    }
}
