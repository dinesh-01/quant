---
paths:
    - 'app/Actions/TestProjects/**'
    - 'app/Http/Controllers/TestProjects/**'
    - 'app/Http/Requests/TestProjects/**'
    - 'app/Concerns/TestProjectValidationRules.php'
---

# Test Projects

## The project prefix freezes once ids are issued

`UpdateTestProject` refuses a prefix change when `test_case_counter > 0`. Those `PREFIX-N` numbers are quoted in bug reports and documents outside this application, and there is no lookup from an old prefix to a new one, so a rename would silently invalidate every reference. Legacy allowed the rename at any time.

The test is on the counter, not on whether any case still exists: deleting the cases does not un-quote the ids.

Only a change is refused, so an ordinary edit does not have to omit the prefix to succeed. `TestProjectController::edit` sends `prefix_locked` so the form can explain the lock rather than let the user type a value that will be rejected.

`manage_test_projects` is a system ability, so all three actions authorize without a scope and read from the global role only. A project role must never let someone create or delete projects — `test_a_project_role_does_not_grant_project_administration` pins that.

Deleting a project cascades to every suite, case, version, step and plan under it. `TestProjectDeleteRequest` requires the project's name to be typed; do not reduce that to a click.
