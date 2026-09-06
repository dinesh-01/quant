---
paths:
    - app/Http/Middleware/HandleInertiaRequests.php
    - 'app/Http/Middleware/**'
---

# Middleware

## currentProject comes from the route, and carries its abilities

`currentProject` is resolved from the route, never from the session. Prefer the `testProject` binding; when the URL binds a plan, platform, build, plan item, tester assignment, case version, case relation, requirement, execution or execution issue instead, walk to that model's project. The specification URLs already carry the project so nodes are linkable, which makes the URL the single source of truth; a session mirror would be a second answer that can disagree, and the symptom is opening a link in a new tab silently changing what another tab shows. This is why there is no `CurrentContext` service and no `EnsureCurrentProject` middleware, both of which the migration plan originally called for.

The prop carries a `can` map (`viewSpecification`, `viewRequirements`, `manageTestPlans`, `viewKeywords`, `viewPlatforms`, `selectPlans`, `viewReports`, `viewCodeTrackers`, `viewIssueTrackers`) so the sidebar can leave out destinations the user would only be refused at. Add to that map when you add a project-scoped area, and gate the nav item on it — do not add a nav item that 403s. `selectPlans` is true when the user can execute or view executions on the project, or holds any plan role in it — that is the tester entry the management list (create_test_plans) cannot be. `viewReports` is true when the user holds `view_plan_metrics` or `view_project_metrics`.

It is a closure, so the gate checks only run when Inertia actually serialises the prop, and they resolve from a role that is already loaded.

## Account status is enforced per request, not at login

EnsureUserIsActive runs on the whole `web` group (bootstrap/app.php) and is the enforcement boundary for `is_active` / `expires_at`. Do not replace it with a login-time check: passkeys, the two-factor challenge and remember-me cookies never pass through the password pipeline, and none of them cover an account deactivated while its session is live.

It logs out, invalidates the session, cycles the remember token (so the cookie cannot sign them back in), then redirects to login with the reason — 401 JSON when the request expects JSON. Flash the message after invalidating so it lands in the fresh session.

Consequence: correct credentials on a deactivated account do create a session, destroyed on the next request before it can be used. That is deliberate — rejecting inside the login pipeline would mean hand-rolling credential verification and forfeiting Laravel's automatic password rehash on login.

RoleResolver keeps its own isActive() check as defence in depth; it is the only one that applies outside HTTP (console, queues).

## Inactive API callers get 401 without a session wipe
EnsureUserIsActive on `/api/*` (or any request without a session) must return 401 JSON and must not call `logout()`, `session()->invalidate()`, or regenerate the CSRF token. Those steps belong to cookie sessions. On API routes the middleware must run after `auth:sanctum` so `$request->user()` is already set — put it on the route group, not the `api` middleware group.
