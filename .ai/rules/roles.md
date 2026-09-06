---
paths:
    - 'app/Actions/Roles/**'
    - 'app/Http/Controllers/Roles/**'
    - 'app/Http/Requests/Roles/**'
    - app/Concerns/ManagesTheDefaultRole.php
    - app/Concerns/RoleValidationRules.php
---

# Roles

## Role edits reach every holder; deletion is refused while in use

Editing a role changes what every holder can do, in every project, at once. `UpdateRole` must therefore call `guardRoleChangeLeavesSomeoneAdministrative()` before saving — stripping `assign_global_roles` from the last role granting it, or clearing `is_super_admin` from the last role that has it, locks out more people than editing one account can.

Deletion is refused for any role held as a global role or assigned to a project or plan. This is not politeness: `users.role_id` is `nullOnDelete` so holders would silently drop to no global role and be denied everything, and the pivots cascade so scoped assignments would vanish — neither leaving a record of what the role granted. This rule subsumes "protect the seeded roles and the last super admin": those cannot be deleted while anyone holds them, and a role nobody holds is harmless. Do not replace it with a list of protected names — names are editable and nothing marks a role as seeded.

The `is_default` role is protected separately via `ManagesTheDefaultRole`, because registration (`CreateNewUser`) reads that flag before anyone holds the role. Keep exactly one: setting it clears the others in the same transaction, unsetting the only one is refused.

`manage_roles` is an administrator-level ability. Whoever holds it can add any ability to any role including their own, so it is equivalent in reach to super admin. Do not grant or describe it as narrow.
