---
paths:
  - 'app/Actions/CodeTrackers/**'
---

# Code Trackers

## One code tracker per project
Save upserts the unique code_trackers.test_project_id row. Test connection probes the saved host with Http:: (no live APIs in tests). Script links authorize with manage_test_cases and hang on the version; CreateTestCaseVersion and CopyTestCase copy them via CopyScriptLinks.
