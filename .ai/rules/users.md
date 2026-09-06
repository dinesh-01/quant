---
paths:
    - 'app/Actions/Users/**'
    - 'app/Http/Controllers/Users/**'
    - 'app/Http/Requests/Users/**'
    - app/Concerns/PreventsAdministratorLockout.php
    - app/Concerns/UserValidationRules.php
    - app/Actions/Users/SetUserPassword.php
---

# Users

## User accounts: two abilities, no delete, lockout guards

`manage_users` and `assign_global_roles` are separate system abilities. Creating or editing an account needs the first; changing `role_id` needs the second, checked only when the role actually changes so one form can serve an actor who may edit people but not promote them.

An absent `role_id` means "unchanged", not "none" — `UserUpdateRequest` substitutes the current role when the key is missing, because the edit form omits the field for an actor without `assign_global_roles`. Present-but-empty still means no global role. Do not collapse those two cases.

There is no delete route or action. Later phases attribute test case versions and executions to a user, so accounts are deactivated, never removed.

Use `forceFill`, not `fill`: `role_id`, `is_active` and `expires_at` are deliberately outside `User::$fillable` so the shared profile form can never reach them. Do not add them to `#[Fillable]`.

Both actions must apply `PreventsAdministratorLockout`. See that trait for the two invariants.

## Two invariants keep the application administrable

Two ways to lose access permanently, both reachable from the user screens and neither guarded in legacy.

1. `guardAgainstSelfLockout` — you cannot deactivate or expire your own account. `EnsureUserIsActive` ends the session on the next request, so the mistake lands before it can be undone. One `isActive()` check covers the flag and a lapsed date together.

2. `guardAgainstRemovingTheLastRoleAssigner` — never leave zero active users able to assign a global role. `assign_global_roles` is readable from a global role only, and a global role can only be granted by someone holding it, so once nobody holds it recovery means editing the database by hand. This applies to other people too: a user manager must not switch off the last administrator.

Call the second guard only when the subject is losing that standing (compare `isRoleAssigner()` before and after filling), so an installation already in the broken state does not have every unrelated edit refused on its way out of it.

Super-admin roles count towards the invariant — they grant everything without listing anything, so the check is `is_super_admin || grants(AssignGlobalRoles)` and is resolved in PHP, not SQL. Expired accounts do not count: they cannot sign in to administer.

Add the same guards to the role management screens — editing a role's abilities can strip `assign_global_roles` from the last role that has it.

## Password reset needs assign_global_roles and ends sessions

Setting another account's password requires `manage_users` **and** `assign_global_roles`. Taking over an account is at least as powerful as changing what it may do, so it takes the same ability. Do not relax this to `manage_users` alone: its holder could then take over a super admin and inherit everything, which would quietly turn `manage_users` from a narrow ability (add and deactivate people) into an administrator-level one.

The reset must also end the account's live sessions and cycle `remember_token`, in one transaction. A reset is normally done because the account is out of its owner's control, and a new password alone leaves an attacker's existing session working until it expires.

Session rows are only authoritative on the `database` driver, so the sweep is skipped otherwise. Note the suite runs with `SESSION_DRIVER=array` (phpunit.xml): a test that wants the sweep must set `config(['session.driver' => 'database'])`, or it silently exercises the skip path and passes for the wrong reason.

This is a stopgap for having no outbound mail. Replace it with a reset link when mail lands, rather than building on it.

## Lockout guards judge the change, never the state

Both guards must only fire when the pending change is what removes the last administrative standing. Judged on state alone, an installation that already has no usable global-role assigner has every unrelated edit refused — blaming a role or account that never granted anything of the sort, and blocking the very edits that would fix it.

UpdateUser does this by comparing isRoleAssigner() before and after filling. guardRoleChangeLeavesSomeoneAdministrative() does it internally via asStored(), which rebuilds the role from getRawOriginal() so the casts still apply. Keep the comparison inside the guard where possible, so a caller cannot get it wrong.

## Invite with a reset link; typed password is the fallback
Creating an account stores a random password and emails Laravel's signed reset link. Until SMTP is set, MAIL_MAILER=log writes that link to the log. Sending a reset link to an existing account needs manage_users and assign_global_roles and ends sessions. SetUserPassword stays as the fallback when mail is unreachable.
