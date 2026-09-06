<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Event log
    |--------------------------------------------------------------------------
    |
    | Days of audit trail to keep when the scheduler runs app:prune-event-log.
    | Must stay at or above EventLogPruneRequest::MINIMUM_KEEP_DAYS (30): a
    | shorter window would let tonight's job erase this morning's evidence.
    |
    */

    'event_log_days' => (int) env('RETENTION_EVENT_LOG_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Execution drafts
    |--------------------------------------------------------------------------
    |
    | Incomplete runs older than this many days (by updated_at) are discarded.
    | Completed history is never touched. Testers who keep editing a draft
    | keep it, because each save refreshes updated_at.
    |
    */

    'execution_draft_days' => (int) env('RETENTION_EXECUTION_DRAFT_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Expired API tokens
    |--------------------------------------------------------------------------
    |
    | Hours after expires_at before sanctum:prune-expired deletes the row.
    | The token already fails auth the moment it expires; this is disk tidy.
    |
    */

    'expired_token_hours' => (int) env('RETENTION_EXPIRED_TOKEN_HOURS', 24),

];
