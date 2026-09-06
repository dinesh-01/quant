---
paths:
  - 'app/Actions/Builds/**'
---

# Builds

## Build writes authorize against the plan
CreateBuild, UpdateBuild and DeleteBuild authorize manage_builds against the plan, not the project, so a plan role can grant or withhold them. Names are unique per plan and trimmed before the uniqueness check. is_open is not is_active: closing stops new results; the build stays listed. DeleteBuild refuses when the build has any execution row — close it instead.
