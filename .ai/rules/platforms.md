---
paths:
  - 'app/Actions/Platforms/**'
---

# Platforms

## Platform catalogue, plan set, and version tags are three jobs
Create/Update/DeletePlatform authorize manage_platforms on the project. SyncPlanPlatforms authorizes manage_plan_platforms on the plan — curating the vocabulary must not imply assigning it. SyncVersionPlatforms authorizes manage_test_cases on the project (tagging a revision is editing the case). DeletePlatform refuses while plan items sit on it. Removing a plan platform deletes those items; adding the first platform is refused while null-platform items exist. New assigns must be open and enable_on_execution (plan) or enable_on_design (version); already-assigned closed ones may stay. CopyPlatformAssignments matches by name across projects and drops the rest — never invent platforms as a side-effect of a copy.
