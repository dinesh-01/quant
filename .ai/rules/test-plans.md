---
paths:
    - 'app/Actions/TestPlans/**'
    - 'app/Http/Controllers/TestPlans/**'
    - 'app/Http/Requests/TestPlans/**'
    - 'app/Concerns/TestPlanValidationRules.php'
    - 'app/Models/TestPlan.php'
---

# Test Plans

## Plan actions scope to the plan, creation scopes to the project

`CreateTestPlan` authorizes `create_test_plans` against the **project**, because no plan exists yet to resolve a plan role against. `UpdateTestPlan` and `DeleteTestPlan` authorize the same ability against the **plan**.

That difference is deliberate. Ability resolution runs plan role, then project role, then global role, so scoping an existing plan's operations to the plan is what makes plan roles reachable at all — and because the most specific role _replaces_ the less specific one rather than adding to it, a plan role granting nothing withholds what the project role would have given. Both directions are pinned by `test_a_plan_role_governs_that_plan_alone` and `test_a_plan_role_can_withhold_what_the_project_role_grants`. Legacy checked plan editing against the project, so plan roles could never affect it.

`is_open` is not `is_active`. `is_active` only decides whether the plan appears in listings; `is_open` decides whether it still accepts execution results, and a closed plan stays readable because its results are the record of a finished round of testing. `RecordExecution` enforces that: a closed plan or build refuses new results.

Deleting a plan requires its name typed. It takes role assignments, builds, linked case versions, tester assignments and execution history (the audit records those counts). Execution history is the least reproducible data in the application. Closing is the reversible alternative and the UI says so.

## GET plans.show is the contents workspace
The plan list stays gated on create_test_plans. Opening a plan as a workspace is allowed with any of create_test_plans, plan_test_cases, manage_builds, manage_plan_platforms, set_test_case_urgency, update_linked_test_case_versions, assign_testers, execute_tests, or view_executions on that plan, so a plan-role linker, build manager or tester can reach it. Settings stay on plans.edit. The Execute sidebar item is the plan selector, not this management list.
