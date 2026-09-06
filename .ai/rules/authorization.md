---
paths:
  - app/Enums/Ability.php
  - 'app/Actions/Authorization/**'
  - app/Providers/AppServiceProvider.php
  - 'app/Policies/**'
  - 'app/Models/{Role,TestProject,TestPlan,User}.php'
  - app/Models/Attachable.php
---

# Authorization

## Abilities are code, roles are data

Every permission is a case of `App\Enums\Ability`. Roles are editable rows whose
`abilities` JSON column holds a list of those enum values, cast with
`AsEnumCollection`. Add a permission by adding an enum case, never by inserting
a row.

Renaming or removing an enum case invalidates roles that still store the old
value, and `AsEnumCollection` throws when it decodes an unknown value. Ship a
migration that rewrites `roles.abilities` alongside any such rename.

## Check abilities through gates, with the scope as the second argument

`AppServiceProvider::configureAuthorization()` registers one gate per ability,
so authorize with `$user->can('manage_test_cases', $project)`,
`Gate::authorize(...)`, or `can:` middleware, passing the `TestProject` or
`TestPlan` being acted upon. Omit the scope only for system abilities.

**Do not register a policy for `TestProject` or `TestPlan`.** These gates are
keyed by ability name and receive those models as arguments, so a policy on
either model takes precedence and would silently shadow every scoped check.

## Role resolution: most specific role wins outright

`App\Actions\Authorization\RoleResolver` resolves plan role, then project role,
then the user's global role. A narrower role **replaces** the broader one rather
than adding to it, so it can withhold abilities the global role grants.

Two exceptions, both deliberate:

- Abilities listed in `Ability::system()` are read from the global role only, so
  no project can delegate application-wide administration.
- A role with `is_super_admin` passes every grant. Restriction abilities
  (`Ability::isRestriction()`, currently execute-only-assigned) stay false, or
  the holder is treated as assigned-only. This is handled inside the resolver
  rather than `Gate::before`, so direct resolver calls and gate checks cannot
  disagree. Do not add a `Gate::before` bypass.

A user with no applicable role is denied, which includes any user who is not
explicitly assigned to a project or plan whose `is_public` is false. Inactive and
expired accounts are denied every ability regardless of role.

## Adding a scoped domain model

New models that own content (suites, cases, plans, executions) should be
authorized against their owning `TestProject` or `TestPlan`, not by adding a new
scope type to the resolver. Resolve the owner and pass it as the gate scope.

## Bulk project visibility must track the single-project gate

`RoleResolver::projectsAllowing()` answers for many projects what `allows()` answers for one, in two queries instead of a pair per project, so an index can be built without an N+1. `plansAllowing()` is the same idea for plans and must stay in step with the plan branch of `effectiveRole()`: plan role first, then a public plan falling through to the project role, then the global role only when the project is public. `PlanSelectorVisibilityTest` cross-checks the two.

That duplication is the risk. If the two drift the symptom is user visible: a project listed that returns 403 when opened, or one hidden that the user still reaches by typing its URL. `TestProjectVisibilityTest::test_the_bulk_resolver_agrees_with_the_single_project_gate` cross-checks them over public, restricted and assigned projects for a globally granted, ungranted, super-admin and expired user. Extend that test whenever either method changes.

Do not reach for a SQL-side version using `JSON_CONTAINS` on `roles.abilities`. Abilities are a JSON enum collection, and pushing the grant check into the database would put a third implementation of the same rule in a place the enum cannot type check.

## Ability::grouped() is what the role form can grant

`Ability::grouped()` is the single declaration of the twelve form headings and the order within each. An ability missing from it can never be granted when editing a role, and one listed twice renders two checkboxes writing the same value — neither fails loudly, so `Tests\Unit\AbilityTest::test_every_ability_appears_in_exactly_one_group` is what catches it. Add every new case to a group in the same change.

Do not rebuild this as a `match` on each case: the map is the only copy, so a group cannot disagree with itself.

`label()` is derived from the case name via `Str::headline`, not listed, so fifty-four labels cannot drift from the cases they describe.

Note that this file is large enough to matter to Larastan: `composer types:check` passes `--memory-limit=512M` because PHP's default 128M is not enough for level 7 here. Run static analysis through the composer script, not bare `vendor/bin/phpstan analyse`.

## Plan and execution attachments resolve against the plan
attachmentScope() is TestProject|TestPlan. Plan and execution files answer with the plan so a plan role can grant them. Download allows the view ability or the manage ability, so a planner without view_executions can still fetch a file they uploaded.

## Restriction abilities do not apply to super admins
ExecuteOnlyAssignedTestCases is a restriction (Ability::isRestriction()), not a grant. RoleResolver::allows() returns false for restrictions when the global role is_super_admin, otherwise Gate::allows() treats the admin as assigned-only and the execute list is empty. Do not check this ability with a super-admin bypass that returns true.
