Next real slice: Phase 11 launch

Phase 10 scheduler is in: daily `app:prune-event-log`, `app:prune-execution-drafts`, and `sanctum:prune-expired`. Windows are `RETENTION_*` in `.env` / `config/retention.php`. The event-log floor is still 30 days. Drafts age by `updated_at`; completed runs are never touched.

Later phases (not started)
Phase 11 launch is parked until Laravel Cloud is chosen and explored (git/CI, deploy, SMTP, queue worker, scheduler cron, backups, security pass). Still parked: OAuth/LDAP/SSO (needs package approval and Q4). Other issue hosts only on request (Q11).

Do not build (Q12 / already decided)
Inventory, RMS, DocBook, plugins, legacy XML, requirement revision tables.
