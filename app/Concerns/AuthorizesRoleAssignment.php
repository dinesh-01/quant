<?php

namespace App\Concerns;

use App\Actions\Authorization\RoleResolver;
use App\Enums\Ability;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

trait AuthorizesRoleAssignment
{
    /**
     * Authorize an actor to change one member's role within a scope.
     *
     * @throws AuthorizationException when the actor may not assign roles here
     * @throws ValidationException when the actor is editing their own access
     */
    protected function authorizeRoleAssignment(
        RoleResolver $roleResolver,
        User $actor,
        TestProject|TestPlan $scope,
        User $member,
    ): void {
        if (! $roleResolver->mayAssignRolesIn($actor, $scope)) {
            throw new AuthorizationException;
        }

        /**
         * Changing your own assignment is how you lock yourself out: on a
         * restricted scope, swapping your role for one without the assign
         * ability leaves nobody able to give it back to you. Someone else has
         * to make the change.
         *
         * An administrator is exempt, because `manage_test_projects` can always
         * undo the damage — and without the exemption they could strand
         * themselves with a self-assignment they were then forbidden to remove,
         * which is the same trap by a different route.
         */
        if ($actor->is($member) && ! $roleResolver->allows($actor, Ability::ManageTestProjects)) {
            throw ValidationException::withMessages([
                'user_email' => 'You cannot change your own role here. Ask someone else who can assign roles in this scope.',
            ]);
        }
    }
}
