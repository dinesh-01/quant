---
paths:
    - 'app/Actions/CustomFields/**'
    - 'app/Http/Controllers/CustomFields/**'
    - 'app/Http/Requests/CustomFields/**'
    - app/Models/CustomField.php
    - app/Models/CustomFieldValue.php
    - app/Models/CustomFieldSubject.php
    - app/Enums/CustomFieldType.php
    - app/Enums/CustomFieldEntity.php
    - app/Concerns/HasCustomFieldValues.php
    - app/Concerns/CustomFieldValidationRules.php
    - app/Concerns/ValidatesCustomFieldValues.php
    - app/Concerns/PresentsCustomFields.php
    - app/Concerns/ScopesCustomFieldSubjects.php
    - 'resources/js/components/custom-fields/**'
    - 'resources/js/pages/custom-fields/**'
---

# Custom fields

## Definitions are global, enablement is per project

`custom_fields` rows are application-wide and `name` is unique across the application; `custom_field_test_project` decides which project shows which field, in what order, and whether it is mandatory there. So `manage_custom_fields` and `view_custom_fields` are system abilities — editing a definition reaches every project that has it enabled — while `assign_custom_fields` is project-scoped. A project role must never reach the catalogue.

Readers rendering fields go through `ResolveCustomFields`, which filters on `is_active`. `TestProject::customFields()` deliberately does not, because the assignment screen has to list switched-off fields in order to switch them back on.

Enabled-but-inactive is the reversible way to stop recording a field; removing the assignment deletes that project's answers through `PurgeCustomFieldValues::forProjectField()`. Keep both offered, and keep the answer count on the screen — it is what the operator weighs before removing.

## Answers hang off the version, and nothing cascades to them

A field defined against "test case" is answered per `TestCaseVersion`, not per `TestCase`, so an older version keeps what it said. `custom_field_values` points at its subject polymorphically, which means no foreign key cascade reaches these rows: every delete path must call `PurgeCustomFieldValues` and every copy path `CopyCustomFieldValues`, and the count belongs in the audit record.

`PurgeCustomFieldValues::forSuiteSubtree()` finds cases through `whereIn('test_suite_id', $suiteIds)`. Scoping it to the project instead would delete every answer in the project along with one suite.

`CopyCustomFieldValues` resolves against the _target_, so a copy into another project keeps only the fields that project records.

New subjects implement `CustomFieldSubject` and use `HasCustomFieldValues`. `customFieldScope()` answers "whose enabled fields apply" (always the project) and `customFieldGateScope()` answers "who may write them" — the plan itself for a plan, because plan roles exist to differ from the project's.

## Validation is derived from the definition

Legacy declared `valid_regexp` and `length_min` and read neither, sent `length_max` to the browser as an attribute and checked it nowhere, and validated four of thirteen types in JavaScript only. `ValidatesCustomFieldValues` builds the rules from the definition instead, so the same field is checked identically on every screen. Never hand-write per-screen rules for a field, and never let a pattern or a length be stored for a type that cannot use it.

Type and entity freeze once an answer exists: what is stored was validated against the definition as it read then.

## An empty answer deletes the row

Legacy's behaviour, kept on purpose: blank means "no answer", so `SaveCustomFieldValues` deletes rather than storing an empty string. A default value fills the form only — storing it would make "left alone" and "agreed with the suggestion" indistinguishable.

Omitting the `custom_fields` key entirely means "unchanged", because the payload carries only what a screen rendered. For multi-value fields the screens post one empty entry to mean "cleared", which `normaliseCustomFieldInput()` strips; requests call that from their own `prepareForValidation()` because several already have that method from another concern.

## Order the writes so a request cannot refuse its own second half

On the specification screens the answers are saved _after_ the suite or version, so a frozen version rejects both together. On the plan screen they are saved _before_, because a plan is its own authorization scope: turning off "open to everyone" leaves a project-role planner without access to the plan they just saved, and the second half of the request would be refused by the state the first half created.

## Execution fields include required-on-execution case fields
ResolveCustomFields::forSubject(Execution) returns Execution-entity fields plus TestCase-entity fields with required_on_execution. Completing a run enforces those; drafts do not. Answers hang off the Execution row.
