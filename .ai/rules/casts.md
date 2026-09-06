---
paths:
    - 'app/Casts/**'
---

# Casts

## Rich text is sanitised by a cast, not by callers

The five rich text fields (TestSuite.description, TestCaseVersion.summary/preconditions, TestCaseStep.actions/expected_results) use the SanitizedHtml cast. Add the cast to any new rich text column; never sanitise in an action or form request instead.

The invariant is 'no row ever holds unsafe markup', which must hold for every write path — actions, factories, future CSV/XLS import, one-off scripts. Per-caller sanitising would be a forgotten call the first time an entry point is added, invisible until something rendered the field.

Only set() sanitises; get() passes through, because the stored value is already clean and reading is a hot path.

Trap: Eloquent builds casts with `new` and passes only the arguments in the cast string, so a constructor dependency is NEVER satisfied. Resolve collaborators with app() inside the method.

The allow-list lives in config/purifier.php. CSS.AllowedProperties is security-critical: position, z-index, display, visibility, opacity and the offset properties are omitted deliberately because they enable clickjacking overlays and hiding content from reviewers. Do not add them.
