---
paths:
    - 'app/Actions/Keywords/**'
    - 'app/Http/Controllers/Keywords/**'
    - 'app/Http/Requests/Keywords/**'
    - app/Models/Keyword.php
    - app/Concerns/HasKeywords.php
    - app/Concerns/ResolvesProjectKeywords.php
    - app/Concerns/KeywordValidationRules.php
---

# Keywords

## Keywords attach to the test case, not the version

A keyword belongs to a TestCase through the keyword_test_case pivot, never to a TestCaseVersion. Legacy stored them per version and then read them off the newest one, so tagging an old version silently did nothing; do not reintroduce that.

A keyword is scoped to one project and unique per project ignoring case. Names are trimmed in KeywordValidationRules::prepareForValidation() so uniqueness and storage agree. Every action that takes keyword ids must run them through ResolvesProjectKeywords, because an action reachable from anywhere cannot rely on a request having checked project membership.

Curating the vocabulary (manage_keywords) and tagging cases (assign_keywords) are separate rights and must stay separate: legacy's edit screen accepted either, so a view-only user could post creates and deletes to it.

Bulk subtree runs have three explicit modes (assign, remove, clear_all) and no default; a bulk replace would discard tags the operator never saw. They record one TestSuiteKeywordsApplied audit event, not one per case, and they use bulk pivot insert/delete over case ids rather than looping models.

Copying a case copies its keywords through CopyKeywordAssignments, which maps across projects by name and drops what the target project has no keyword for.
