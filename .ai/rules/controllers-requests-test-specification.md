---
paths:
    - 'app/Http/{Controllers,Requests}/TestSpecification/**'
---

# Controllers Requests Test Specification

## Specification requests carry the shape, controllers only wire

Each write request exposes a typed accessor (`suiteAttributes()`, `caseAttributes()`, `versionAttributes()`, `stepAttributes()`) returning an array shape that matches the action's parameter exactly. Controllers call that, never `safe()->only()` — a loose `array<string, mixed>` cannot satisfy a shape with a required key, and Larastan rejects it at level 7.

`optionalText()` maps an empty string to null, so a cleared textarea and an untouched one store the same value. `requiredEnum()` and `requiredString()` narrow past nullable getters that validation has already guaranteed.

Requests have no `authorize()` method. Authorization is the action's job, so the base `SpecificationFormRequest` only narrows route bindings. Read endpoints do check `Gate::authorize(Ability::ViewTestCases, $project)` in the controller, because there is no action involved.

Abilities are project-scoped, so one check covers every node in a tree. Do not add per-node checks; there is no per-node grant to check.
