---
paths:
  - 'app/Actions/TestPlanItems/**'
---

# Test Plan Items

## Plan items pin a version, optionally a platform
Link/Unlink/Reorder authorize plan_test_cases on the plan. Urgency uses set_test_case_urgency; bumping uses update_linked_test_case_versions. The version must belong to the plan's project. A plan with no platforms accepts platform_id null only; a plan with platforms requires one of those platforms. Unique slot is (plan, version, platform) — refuse a duplicate rather than hitting the unique index. UpdateLinkedTestCaseVersion only moves forward to a newer version of the same case, and refuses if that version+platform is already linked. Reorder is not audited.
