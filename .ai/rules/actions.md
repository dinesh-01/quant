---
paths:
    - 'app/Actions/**'
---

# Actions

## Domain actions authorize themselves

Every write action in `app/Actions/` calls `Gate::forUser($user)->authorize(Ability::X->value, $scope)` as its first statement, and takes the acting `User` as its first parameter rather than reading `auth()`.

The check lives in the action, not the controller, so a new controller or console command cannot forget it, and so the denied path is testable without an HTTP route. Controllers and form requests may validate on top, but the action stays the enforcement point.

The gate scope is the owning `TestProject` or `TestPlan`, reached with `loadMissing()` on the relation rather than a fresh query. Creating a plan (or a project-owned catalogue row such as a platform) authorizes against the project, because no plan exists yet to resolve a plan role against. Writes on an existing plan — platforms, builds, linking, urgency, bumping a version — authorize against the plan, so a plan role can grant or withhold them. Cross-project operations authorize both ends: reading the source needs `view_test_cases` on its project, writing the copy needs `manage_test_cases` on the target.

Two deliberate exceptions, both documented in their own docblocks: `AllocateExternalId` is an internal collaborator no request reaches, and `CopyTestCase::duplicate()` skips the check for callers such as `CopyTestSuite` that have already authorized the whole subtree. Never call `duplicate()` from a controller.
