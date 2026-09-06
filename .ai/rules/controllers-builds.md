---
paths:
  - 'app/Http/Controllers/Builds/**'
---

# Controllers Builds

## Create a build under the plan, edit the build alone
Create is nested under the plan because no build exists yet. Editing binds the build so a nested plan id cannot disagree. Writes authorize manage_builds against the plan.
