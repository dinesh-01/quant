---
paths:
    - 'app/Actions/RoleAssignments/**'
    - 'app/Http/Controllers/RoleAssignments/**'
    - 'app/Http/Requests/RoleAssignments/**'
    - app/Concerns/AuthorizesRoleAssignment.php
    - app/Concerns/PresentsScopeMembers.php
    - app/Concerns/PreventsAdministratorLockout.php
---

# Role Assignments

## Role assignment has an administrator escape hatch, and refuses self-edits

Use `RoleResolver::mayAssignRolesIn($user, $scope)` for anything that grants or revokes a role. Never check `assign_project_roles` / `assign_plan_roles` on the scope directly.

The reason is a deadlock. On a **restricted** project or plan, ability resolution returns no role at all for anyone not already assigned, and assignment is the only way to become assigned — so checking only the scoped ability means the first role on a private scope can be granted by nobody but a super admin, and an administrator who made a project private has locked themselves out of fixing it. `mayAssignRolesIn()` therefore also passes anyone whose global role grants `manage_test_projects`. Legacy avoided this by making role assignment a global right; here the abilities are scoped (R3), so the escape hatch must be explicit. Pinned by `test_an_administrator_can_seed_the_first_role_on_a_private_project` and the plan equivalent.

`AuthorizesRoleAssignment` also refuses to let an actor change or remove **their own** assignment, because swapping your role for one without the assign ability is how you strand yourself on a restricted scope. Administrators are exempt: they can always restore access, and without the exemption they could strand themselves with a self-assignment they were then forbidden to remove — the same trap by another route.

A role with `is_super_admin` cannot be assigned to a single scope. The flag is only honoured on a user's global role, so offering it would promise powers the resolver ignores.

One role per user per scope, enforced by the pivots' composite primary keys, so assigning again must update the pivot rather than insert. `syncWithoutDetaching` does that; `attach` would violate the key.
