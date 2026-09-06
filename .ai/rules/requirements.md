---
paths:
  - 'app/Actions/Requirements/**'
---

# Requirements

## Coverage pins versions, not heads
Requirement coverage is (requirement_version_id, test_case_version_id). Creating a new version of either side copies wording but must not copy or move coverage links — that is the freeze-on-new-version rule. There are no revision tables; versions only. doc_id is user-supplied and unique per project (specs and requirements each have their own unique). Specs nest like suites with MAX_DEPTH 10. Authorize manage_requirements for authoring, unfreeze_requirements to reopen, manage_requirement_coverage for links.

## No XML import or export
Requirement XML import/export were removed. Do not add XML routes, actions, or UI. Later import is not XML.

## Watch mail is queued and fires on delete
RequirementChangedNotification implements ShouldQueue and afterCommit(). It stores primitives, not the Requirement model, so a queued send still works after delete. NotifyRequirementWatchers excludes the actor. Version update and new version already notify; delete notifies before the row is removed and the mail links to the project requirements page. Do not add another watch table. SMTP is Phase 11 — the default mailer is log.
