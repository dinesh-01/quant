---
paths:
    - 'app/Actions/TestSpecification/**'
---

# Test Specification

## External ids come only from AllocateExternalId

`PREFIX-N` numbers must be taken from `AllocateExternalId`, which increments `test_projects.test_case_counter` under `lockForUpdate()` inside a transaction. Never derive one from `MAX(external_id) + 1`, and never set `test_case_counter` directly — it is excluded from `#[Fillable]` for that reason.

`TestCaseFactory` calls the same allocator, so factory-built and action-built cases in one project cannot collide. Keep it that way if the factory changes.

Known gap: no test proves the lock holds under real contention, because `RefreshDatabase` runs each test in a single transaction on a single connection where a lock cannot compete with itself. `ExternalIdAllocationTest` covers a contiguous duplicate-free sequence and asserts the `select ... for update` is actually issued, which is the available proxy. Proving real contention needs a second connection or forked processes.

## Deleting or copying a node must reach its attachments

No foreign key reaches a polymorphic link, so nothing here cascades to `attachments`. `Delete{TestSuite,TestCase,TestCaseVersion}` call `PurgeAttachments` before the delete and put the count in their audit record; `CreateTestCaseVersion`, `CopyTestCase` and `CopyTestSuite` call `CopyAttachments`. Any new action that deletes or copies a suite, case or version has to do the same — see `.ai/rules/attachments.md`.

## What the specification trail records, and what it does not

The audit subject is always the NAMED thing a reader would look up. Suite acts point at the suite; version and step acts point at the TEST CASE, because a version has no name and pointing the log at one renders a bare id. Version and step numbers go in properties.

Creating a case records one act, not two — its first version is part of creating it. A subtree copy records once for the whole copy, not per node.

Deliberately NOT recorded: the three reorder actions (presentation only, highest-volume write in the specification) and AllocateExternalId (internal plumbing, no intent of its own). UpdateTestCaseVersion strips updater_id from its change set as churn. Freezing an already frozen version records nothing, being a documented no-op.

Step edits ARE recorded despite versions being a history mechanism: a step's expected result is what a tester is judged against, and the version's updater_id does not move when a step changes.

## New versions copy design-time platforms
CreateTestCaseVersion must sync the source version's platforms onto the new one. Platforms hang off the version, so unlike keywords they do not carry over by themselves. CopyTestCase (and CopyTestSuite via duplicate()) copies them through CopyPlatformAssignments, which maps across projects by name and drops what the target project has no platform for.

## New versions copy automation-script links
CreateTestCaseVersion and CopyTestCase call CopyScriptLinks. Links hang off the version, so a new revision or a copied case keeps the same repository path. Requirement coverage is still not copied.

## Case relations sit on the case
Links are between cases, not versions. Related is symmetric (the reverse row is the same link and is refused). Depends-on and blocks keep direction. Authorize manage_test_cases on the source case's project. The case pane lists outgoing and incoming with type labels; do not add a version-scoped copy.

## No XML import or export
Suite and requirement XML import/export were removed on purpose. Do not add XML routes, actions, or UI. Later case import is CSV/XLS and documentation is Google Drive.

## Code trackers are one row per project
`code_trackers.test_project_id` is unique. Save upserts that row. Script links authorize with `manage_test_cases`, not the tracker abilities. `view_code_trackers` / `manage_code_trackers` are only for the project catalogue. Still drop inventory, RMS, DocBook, and plugins.

## Ghost Step tokens follow the host field
ExpandGhostMarkup takes the host field name. A Step token in expected_results substitutes the source step's expected_results; elsewhere it substitutes actions. The stored token is unchanged. Pass summary, preconditions, actions, or expected_results from the spec pane and the run pane.
