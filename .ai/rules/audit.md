---
paths:
    - 'app/Actions/Audit/**'
---

# Audit

## The audit trail is called explicitly and redacts from $hidden

Writes are recorded by calling AuditLogger::record() from the action, not by a model observer: an observer cannot tell an administrator's edit from the subject editing their own profile, has no actor in console context, and would log framework-driven saves.

AuditLogger::changes() must be called BEFORE save, while the model is dirty. It records attributes the model marks #[Hidden] as ['changed' => true] with no values — that is what keeps password hashes and two-factor secrets out of a widely readable table, and it means a newly hidden attribute is covered without touching this class. Never hand-build properties from a model's raw attributes.

An edit that changed nothing is not recorded. Where the write is already transactional, record inside the transaction. Record deletions BEFORE the delete and copy anything the log must display into properties, because the subject morph will not resolve afterwards.
