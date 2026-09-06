---
paths:
  - 'app/Http/Controllers/Executions/**'
---

# Controllers Executions

## Store must type-hint the plan item
ExecutionController::store must type-hint TestPlanItem $testPlanItem even if the action reads it from the route. Laravel only implicit-binds models that appear on the controller signature. Without that hint, $request->route('testPlanItem') is the raw id string and the run 404s.
