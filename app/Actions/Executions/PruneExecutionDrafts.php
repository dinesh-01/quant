<?php

namespace App\Actions\Executions;

use App\Actions\Attachments\PurgeAttachments;
use App\Actions\Audit\AuditLogger;
use App\Actions\CustomFields\PurgeCustomFieldValues;
use App\Enums\AuditAction;
use App\Models\Execution;

/**
 * Discards abandoned drafts older than a given age.
 *
 * Completed runs are never touched. Age is `updated_at`, so a tester who
 * keeps saving a draft keeps it. Attachments and custom-field answers have
 * no FK cascade, so they are purged first — same duty as DeleteExecution.
 *
 * No acting user and no gate: this is the scheduler path. Shell access is
 * the privilege. Never call it from a controller.
 */
final class PruneExecutionDrafts
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PurgeAttachments $purgeAttachments,
        private readonly PurgeCustomFieldValues $purgeCustomFieldValues,
    ) {}

    /**
     * @return int how many drafts were discarded
     */
    public function __invoke(int $keepDays): int
    {
        $cutoff = now()->subDays($keepDays);
        $discarded = 0;
        $attachments = 0;
        $customFieldValues = 0;

        Execution::query()
            ->where('is_draft', true)
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($drafts) use (&$discarded, &$attachments, &$customFieldValues): void {
                foreach ($drafts as $draft) {
                    $attachments += $this->purgeAttachments->forExecution($draft);
                    $customFieldValues += $this->purgeCustomFieldValues->forExecution($draft);
                    $draft->delete();
                    $discarded++;
                }
            });

        $this->audit->record(AuditAction::ExecutionDraftsPruned, null, null, [
            'keep_days' => $keepDays,
            'before' => $cutoff->toDateTimeString(),
            'discarded' => $discarded,
            'attachments' => $attachments,
            'custom_field_values' => $customFieldValues,
        ]);

        return $discarded;
    }
}
