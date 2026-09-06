---
paths:
    - 'app/Console/Commands/**'
---

# Commands

## app:make-administrator is the only bootstrap and recovery path

`php artisan app:make-administrator` is how a fresh installation gets its first administrator, and the documented way back from a lockout. Nothing else can do it: registration grants the `is_default` role, system abilities are readable from a global role only, and a global role can only be granted by someone already holding `assign_global_roles`.

Keep these properties when changing it:

- Non-interactive via `--name`, `--email`, `--password`, so provisioning scripts work; prompts only fill the gaps.
- An existing email **promotes** rather than erroring, and clears `is_active` / `expires_at` — an unusable administrator is no use as a recovery path.
- Reuse an existing `is_super_admin` role; only create one when the roles were never seeded, or every run accumulates another.
- Set `email_verified_at`, or the new account lands on the verification prompt that a fresh install has no mail to satisfy.

It can promote anyone by design. That needs shell access, which is already more privilege than any role grants, so do not add role checks to it.

## Daily housekeeping is three scheduled commands
Housekeeping is three daily scheduled commands: app:prune-event-log (same PruneEventLog action as the screen, unattended(), keep-days floor is EventLogPruneRequest::MINIMUM_KEEP_DAYS), app:prune-execution-drafts (updated_at, completed runs stay), and sanctum:prune-expired. Windows live in config/retention.php. withoutOverlapping + onOneServer. Shell access is the privilege — no actor, no gate.
