---
paths:
  - 'app/Actions/IssueTrackers/**'
---

# Issue Trackers

## One issue tracker per project
Save upserts the unique issue_trackers.test_project_id row. Credentials live in encrypted settings (never echoed; empty token on update keeps the stored one). Test connection uses Http:: and treats 401/403 as failure because create-from-fail needs a working token. Testers create issues from completed failed or blocked runs with execute_tests, not manage_issue_trackers. Jira Cloud REST v3 only — no SOAP or direct-database adapters.
