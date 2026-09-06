---
paths:
  - 'app/Http/Controllers/Platforms/**'
---

# Controllers Platforms

## Platform HTTP matches the keyword catalogue
The vocabulary is nested under the project; edit/update/destroy bind the platform alone so a nested project id cannot disagree. Plan assignment is PUT plans/{testPlan}/platforms (manage_plan_platforms). Version assignment is PUT test-case-versions/{testCaseVersion}/platforms (manage_test_cases). Delete is refused while plan items exist — show plan_items_count and disable the button.
