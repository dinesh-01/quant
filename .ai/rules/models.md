---
paths:
    - app/Models/TestCase.php
    - 'app/Models/{TestSuite,TestCase,TestCaseVersion,TestCaseStep}.php'
    - app/Models/TestProject.php
---

# Models

## App\Models\TestCase collides with Tests\TestCase and with Pint

The domain model is deliberately named TestCase, so two collisions have to be handled.

In tests, import it aliased: `use App\Models\TestCase as TestCaseModel;`. The test class itself still extends `Tests\TestCase`.

Pint's `php_unit_method_casing` fixer treats any class named `TestCase` as a PHPUnit test class and rewrites `testProject()` to `test_project()`, silently breaking the relations. The fixer is therefore disabled in `pint.json`. Do not re-enable it, and do not rely on `notPath` — it is ignored when Pint is given explicit paths, which is how this project runs it (quanta is not a git repo, so `--dirty` is unavailable). Test methods stay snake_case by convention, not by fixer.

## Suite tree queries, ordering and the InnoDB cascade limit

Laravel 13's query builder has no CTE support and no package was added, so `TestSuite::subtree()` and `path()` are raw `WITH RECURSIVE` statements bound through `DB::select` and hydrated. The anchor row casts its path column with `CAST(... AS CHAR(1000))`; without it MySQL sizes the column from the anchor and the recursive part overflows.

Render whole trees with `TestSuite::treeFor($project)`, which is one query that attaches every `children` relation in memory. Never lazy load `children` in a loop.

Ordering is `sort_order` on suites, cases and steps, and is intentionally not unique: MySQL cannot defer a unique check to commit, so reordering would collide mid-transaction. Reorder actions renumber contiguously.

Deletes rely on real FK cascades from project down to step. InnoDB abandons cascading after 15 levels, so suite nesting must stay under roughly 12 levels or a project delete fails.

## test_case_counter is the only source of PREFIX-N numbers

`test_projects.test_case_counter` records the last `PREFIX-N` number handed out. It is deliberately absent from `#[Fillable]` so no request or factory can move it.

Allocate a number only inside a transaction that takes `lockForUpdate()` on the project row, then write it to `test_cases.external_id`, which is `UNIQUE (test_project_id, external_id)`. Users quote these ids, so duplicates and gaps are both visible.

`TestCaseFactory` calls the same `AllocateExternalId` action, so factory-built and action-built cases in one project cannot collide on the unique index. Keep it that way: deriving the factory's id from `MAX(external_id) + 1` leaves the counter at zero, and the next real allocation then hands out a number that is already taken.

## Search terms must go through the matching scope

`TestCase::matching($term)` is the only sanctioned way to search cases. It escapes `\`, `%` and `_` before the term reaches `LIKE`, in that order so the backslashes it introduces are not re-escaped.

This is not cosmetic. `whereLike` does not escape anything, so an interpolated term of `%` matches every case in the project and `_` matches any single character. Legacy had exactly that bug. Never build a `LIKE` pattern from request input anywhere else.

A term of all digits also matches `external_id`, so pasting `42` finds `PREFIX-42`. That is why the search request allows a one-character term: requiring two would make every id below 10 unsearchable, and the escaping already makes a short term harmless.

The escaping assumes MySQL's default backslash escape character. Under `NO_BACKSLASH_ESCAPES` it would need an explicit `ESCAPE` clause, which `whereLike` cannot express.
