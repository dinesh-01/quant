<?php

namespace App\Concerns;

use App\Models\Role;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;

trait PresentsScopeMembers
{
    /**
     * The member list and the roles that may be given, in two queries.
     *
     * Two different sets come out of one read of the roles table. Names are
     * looked up across *every* role, so a member holding a super admin role
     * still shows what it is called; the dropdown offers only the roles that
     * may be assigned to a single scope. Reusing the narrowed set for both
     * would render a blank name for a member whose role is no longer offered.
     *
     * Roles are matched in memory rather than eager loaded through the pivot
     * because the same small set is needed for the dropdown anyway.
     *
     * @return array{members: list<array<string, mixed>>, roles: list<array<string, mixed>>}
     */
    protected function memberProps(TestProject|TestPlan $scope, User $actor): array
    {
        $roles = Role::query()->orderBy('name')->get();

        $nameById = $roles->pluck('name', 'id');
        $assignable = $roles->reject(fn (Role $role): bool => $role->is_super_admin);

        $members = $scope->members()
            ->orderBy('name')
            ->get()
            ->map(function (User $member) use ($nameById, $actor): array {
                $roleId = (int) $member->getAttribute('pivot')->role_id;

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role_id' => $roleId,
                    'role_name' => $nameById->get($roleId, 'Unknown role'),
                    'is_self' => $member->is($actor),
                ];
            })
            ->all();

        return [
            'members' => array_values($members),
            'roles' => array_values($assignable->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
            ])->all()),
        ];
    }
}
