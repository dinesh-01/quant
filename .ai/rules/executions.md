---
paths:
  - 'app/Actions/Executions/**'
---

# Executions

## Executions freeze the version number
A run stores the case version number at save time. Authorize execute_tests against the plan. is_open on the plan and the build refuses new results; history stays readable. execute_only_assigned_test_cases limits recording to tester_assignments for that item+build. One draft per (item, build, tester); completing that draft is the history row. Testers can type an issue id or create one in the project's tracker from a completed failed or blocked run (`execute_tests`, not `manage_issue_trackers`). Abandoned drafts older than `retention.execution_draft_days` are discarded by `app:prune-execution-drafts` (age is `updated_at`; completed runs are never touched).

## Do not firstOrNew unfillable execution step keys
ExecutionStep is fillable only for status and notes. firstOrNew(['test_case_step_id' => ...]) fills the lookup key and silently drops it, so MySQL rejects the insert (field has no default). Look the row up, then assign test_case_step_id and sort_order on the model.
