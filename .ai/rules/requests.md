---
paths:
    - 'app/Http/Requests/**'
---

# Requests

## Sibling suite names are checked in validation, never in the database

Suite names must be unique among siblings, but there is deliberately no unique index for it. `test_suites.parent_id` is NULL for root suites and MySQL treats NULLs as distinct, so a `UNIQUE (test_project_id, parent_id, name)` index would silently not apply at the project root — the one place users would notice. Legacy had no DDL constraint either.

Enforce it in the form request instead, scoping the rule to the same `test_project_id` and the same `parent_id` (including the null case) and ignoring the suite being edited. Do not "fix" this by adding a unique index, and do not switch root suites to a sentinel `parent_id` of 0 — that would break the self-referencing foreign key.

## Fold checkbox on before the boolean rule
Radix/HTML checkboxes submit "on". Laravel's boolean rule only accepts true/false/0/1/"0"/"1", so a create looks like it failed (422) and stores nothing. Fold flags with $this->boolean() in prepareForValidation. Request::boolean() already treats "on" as true.
