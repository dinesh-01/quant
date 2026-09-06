---
paths:
    - database/migrations/**
    - app/Models/**
---

# Schema

## This is a clean-slate rebuild, not a port of the legacy schema

The application replaces a legacy PHP test-management tool (documented under
`../../testlink-code/docs/`). No legacy data is imported, so legacy table names,
column names and ids carry no weight. Use idiomatic Laravel schema: `id()`,
snake_case columns, `timestamps()`, and real foreign keys with an explicit
delete behaviour.

Consult the legacy DDL and `DATABASE.md` for what the domain _means_ and which
uniqueness rules users rely on, never for how to name or key a table.

## The legacy shared-key tree is not reproduced

Legacy stored every entity in one `nodes_hierarchy` table sharing a primary key
with per-type tables. Do not recreate it. Each entity gets its own table; use a
self-referencing `parent_id` only where nesting is genuinely recursive (test
suites, requirement specs) and a plain foreign key elsewhere. Ordering lives in
a `sort_order` column on the table that needs it.

## Models declare fillable and hidden with attributes

Follow the starter kit: `#[Fillable([...])]` and `#[Hidden([...])]` class
attributes, a `casts()` method, and a `@property` docblock listing columns.

Mirror a column default in `$attributes` when application code reads the value
before the model is reloaded. `User::$attributes['is_active']` exists because
authorization asks a freshly created user whether it is active.

## Specification text fields are sanitised HTML, and are not yet rendered as HTML

`test_suites.description`, `test_case_versions.summary` / `preconditions` and `test_case_steps.actions` / `expected_results` hold HTML that has been through the `SanitizedHtml` cast. Any new rich text column must use that cast — see `.ai/rules/casts.md`.

Legacy stored CKEditor HTML and rendered it unescaped, which was the tool's worst stored-XSS hole. Storage is now safe, but the UI still edits these with a plain textarea and renders them as text. Do not add `dangerouslySetInnerHTML` or a rich-text editor until open question Q5 (which editor) is settled: half-rendering them is worse than either end state.

## Suite nesting is capped at MAX_DEPTH

`TestSuite::MAX_DEPTH` is 10 and must be enforced by any action that creates, moves or copies a suite, using `depth()` for the target and `descendantDepth()` for the subtree travelling with it.

This is not cosmetic. Deletes rely on foreign key cascades running project → suite → nested suite → case → version → step, and InnoDB abandons cascading after 15 levels, so an uncapped tree could make its own project undeletable. 10 leaves margin for the fixed links below the suite.

Both helpers run a recursive CTE, so call them once per operation rather than inside a loop.

## Plan items pin a version, optionally a platform
A TestPlanItem points at a test case VERSION, not a case. Uniqueness is (plan, version, platform). MySQL treats NULL as distinct, so use a functional unique key (ifnull(platform_id, 0)) — not a stored generated column: InnoDB refuses a foreign key on a column a generated column reads. Urgency lives on the item; importance stays on the version. Design-time platform assignments hang off the version (platform_test_case_version), the opposite of keywords.
