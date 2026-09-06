---
paths:
    - 'app/Http/Controllers/Audit/**'
---

# Controllers Audit

## The event log composes its sentences at read time

Rows store facts (action, properties, an instant); the label comes from AuditAction::label() and the timestamp is formatted in the reader's locale on the page. Never store or send a pre-rendered sentence — that was legacy's mistake and it makes wording unimprovable and the log unfilterable.

Always eager load ['user', 'subject']. subject is a morphTo, so resolving it per row is one query per record per type.

A subject may not resolve (deleted role, cascaded plan). Fall back to properties['name'], which the delete actions copy in for this purpose.

Pruning is gated on manage_event_log, NOT view_event_log — destroying evidence is a different act from reading it. It takes a retention period with a 30-day floor, never a free cutoff or a 'clear all', or it becomes a way to erase what the actor just did. It records itself after the delete so the account of it survives.
