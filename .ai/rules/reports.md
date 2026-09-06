---
paths:
  - 'app/Reports/**'
---

# Reports

## Latest completed run is MAX(id) of non-drafts
Reports score a plan item from its latest completed execution on the selected build: is_draft = false, highest id wins, unique per test_plan_item_id. Items with no completed run are not_run. Drafts never count. Do not use executed_at or created_at for "latest".

## Timeline is activity; baselines denormalize items
Status-over-time groups completed executions (is_draft=false) by DATE(executed_at) and counts every run that day. It is not latest-per-item. Latest completed run remains MAX(id) of non-drafts for PlanStatusReport.

Baselines snapshot that live status report onto report_baselines (counts + item rows JSON). Compare against live or list; do not join live plan items to reconstruct a snapshot. Authorize view_plan_metrics for save and read. XLSX uses the same columns as the plan-status CSV.
