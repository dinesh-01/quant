---
paths:
  - 'resources/js/pages/executions/**'
---

# Pages Executions

## Execution run matches execution.html
public/ui-mockups/execution.html is the visual source of truth for the run workspace. Keep the filter toolbar, assigned-cases rail (All/Untested/Failed, Pass/Now/Fail/Blk), step mini-verdicts, evidence row, and Pass/Fail/Blocked/Skip verdict bar. Live numbers, cases, and assignments come from ExecutionNavigatorController — do not hard-code the mock's sample figures.
