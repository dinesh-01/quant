---
paths:
  - 'app/Actions/TesterAssignments/**'
---

# Tester Assignments

## Tester assignments are per item and build
Assign/Unassign/Update authorize assign_testers on the plan. The assignee must be active and allowed execute_tests on that plan. Unique slot is (item, build, user). CopyTesterAssignments stays on one plan, resets status to open, and skips slots the target already has so a second press is a no-op.
