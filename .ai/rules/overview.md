---
paths:
  - 'resources/js/pages/overview/**'
---

# Overview

## Overview matches the static mock
public/ui-mockups/index.html is the visual source of truth for Overview. Keep the same labels, table columns (including Assignees, Priority, Issue), donut legend (Passed/Failed/Blocked/Untested), icon set, and sentence structure. Live numbers come from ProjectOverviewReport — do not hard-code the mock's sample figures.

## Overview mirrors the static mock, data included
public/ui-mockups/index.html is the source of truth for Overview: labels, columns, donut legend, icons and sentences. The figures are matched by seeding, not by hard-coding — OverviewMirrorSeeder shapes Checkout to 482 cases / 421 automated / Build 4.7 then 4.8 at 211-28-14-45, and OverviewMirrorSeederTest asserts every one of those numbers. Change the seeder, never the page, when a number is wrong. Two mock numbers are unreachable together and are documented in the seeder: the donut legend (kept) implies 83% pass, not the mock's 82%, and Checkout · Regression's progress reads 85%, not 81%.

## Overview has no Export button
Overview does not show Export, even though public/ui-mockups/index.html does. Keep Start a run. Reports stay reachable from the sidebar, not from this header.
