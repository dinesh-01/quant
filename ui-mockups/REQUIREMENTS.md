# Quanta — Test Management UI redesign (static mockups)

> **Purpose of this folder.** A design-only, static HTML prototype of a fresh, more
> user-friendly UI for the Quanta test-management app. **No backend, no build step.**
> Everything is hardcoded mock data. The *dynamic* implementation is a separate
> project — this folder is the visual + interaction spec it should match.

Design direction chosen for pass 1: **Clean & modern SaaS** (light theme, generous
whitespace, soft shadows, single violet accent). This is a starting point — we iterate
from feedback.

---

## How to view

Just open the files in a browser — no server required.

```bash
open ui-mockups/index.html
```

Or serve the folder (already under MAMP htdocs):

```
http://localhost/vv/quanta/ui-mockups/index.html
```

## Files

| File | Screen | Notes |
| --- | --- | --- |
| `index.html` | **Dashboard / Overview** | KPI tiles, active runs, latest-build donut, "needs attention", activity feed |
| `test-cases.html` | **Test Cases / Specification** | Suite tree · case list · detail panel with steps, versions, automation link |
| `requirements.html` | **Requirements & Coverage** | Coverage summary, requirement list, traceability matrix |
| `test-plans.html` | **Test Plans** | Plan cards with build/milestone/platform, progress, assignees |
| `execution.html` | **Execution runner** | Case rail · per-step pass/fail · evidence · verdict bar · issue linking |
| `reports.html` | **Reports** | Result donut, results-by-suite bars, pass-rate trend, baselines, CSV/XLSX |
| `assets/styles.css` | Design system | All tokens as CSS variables — re-skin from here |
| `assets/app.js` | Shared behaviour | Inline SVG icon sprite + segmented/tab/verdict toggles |

## Design system (tokens live in `assets/styles.css` `:root`)

- **Accent:** `--primary #6d5efc` (violet — deliberately *not* the usual QA-tool blue).
- **Status colors:** pass `--success`, fail `--danger`, blocked `--warning`,
  skipped/untested `--neutral`, running `--info`. Used consistently as `.pill`,
  `.progress .seg`, `.status-dot`.
- **Surfaces:** `--bg` app background, `--surface` cards, `--border` hairlines.
- **Shape:** radii `--r-*`, shadows `--shadow*`. Sidebar width + topbar height are vars.
- Reusable primitives: `.card`, `.btn`, `.pill`, `.tag`, `.prio`, `.avatar`,
  `.progress`/`.meter-row`, `.table`, `.toolbar`/`.filter`/`.seg-control`,
  `.stat`, chart helpers (`.donut`, `.bars`, `.line-chart`), `.split` layouts.

## What is intentionally static (mock) right now

- All data (projects, cases, requirements, plans, runs, numbers, avatars, charts).
- Charts are hand-plotted SVG/CSS — **not** driven by data.
- Nav links move between the 6 pages; every other control is visual only
  (buttons, filters, search, project switcher do nothing).
- Only three interactions are wired in `app.js`: segmented controls, tab panels,
  and execution verdict buttons — purely to demonstrate feel.

## What the dynamic project will need to wire (hand-off checklist)

- **Auth & shell** — real user in sidebar footer, project switcher populated from the
  user's projects, `⌘K` global search.
- **Dashboard** — live counts, active runs from plans, latest-build result aggregation,
  activity from the audit/event log.
- **Test Cases** — suite tree from suites API; case list with server pagination
  (cursor-based per the existing `/api/v1`); detail panel loads steps + versions;
  freeze/unfreeze, copy/move, keyword filter, automation (code-tracker) links.
- **Requirements** — requirement specs/versions, coverage links to cases, watchers,
  traceability matrix computed from coverage + latest execution result, export.
- **Test Plans** — plans + builds + milestones + platforms + tester assignments;
  progress computed from executions.
- **Execution** — the core loop: load assigned cases for a plan/build, record
  per-step + overall verdict, attach evidence, link/create issues, save draft,
  submit & advance. Should map to existing Execution / ExecutionStep / ExecutionIssue.
- **Reports** — aggregations per build/suite, trend across builds, baselines
  (save/compare), CSV + XLSX export.

## Open design questions (for feedback)

1. Sidebar: keep dark, or make it light to match the content area?
2. Accent color — violet as shown, or align to a Freshworks/brand palette?
3. Density — is the current row height right, or do power users want a compact mode?
4. Execution: step-by-step verdicts (as shown) vs. a single case-level verdict + notes?
5. Do we want a dark theme variant in the next pass?

## Not in scope (matches product's stated non-goals)

Legacy XML import/export, plugin marketplace, OAuth/LDAP/SSO screens,
cloud-deploy/SMTP/backup admin.

---

*Iteration log*

- **v0.1** — first pass: 6 screens, clean modern SaaS, dark sidebar, violet accent.
