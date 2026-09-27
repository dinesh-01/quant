---
paths:
  - 'app/Http/Controllers/Executions/**'
---

# Controllers Executions

## Store must type-hint the plan item
ExecutionController::store must type-hint TestPlanItem $testPlanItem even if the action reads it from the route. Laravel only implicit-binds models that appear on the controller signature. Without that hint, $request->route('testPlanItem') is the raw id string and the run 404s.

## Submit and next advances the runner
ExecutionController::store redirects after a completed run: Pass or Skip goes to the next untested case on that build (or the plan list when none remain). Fail or Blocked stays on the same case with ?issue=1 so testers can link or create an issue. Skip (complete + not_run) must not call RecordExecution. Drafts still return back().
