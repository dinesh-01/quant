<?php

namespace App\Console\Commands;

use App\Actions\Executions\PruneExecutionDrafts;
use Illuminate\Console\Command;

/**
 * Discards abandoned execution drafts. Completed history is left alone.
 */
class PruneExecutionDraftsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:prune-execution-drafts
                            {--days= : Days of drafts to keep (defaults to retention.execution_draft_days)}';

    /**
     * @var string
     */
    protected $description = 'Discard abandoned execution drafts older than the retention window';

    public function handle(PruneExecutionDrafts $pruneExecutionDrafts): int
    {
        $keepDays = $this->keepDays();

        if ($keepDays < 1) {
            $this->components->error('Keep at least one day of drafts.');

            return self::FAILURE;
        }

        $discarded = $pruneExecutionDrafts($keepDays);

        $this->components->info(__('Discarded :count abandoned :drafts older than :days days.', [
            'count' => $discarded,
            'drafts' => $discarded === 1 ? 'draft' : 'drafts',
            'days' => $keepDays,
        ]));

        return self::SUCCESS;
    }

    private function keepDays(): int
    {
        $days = $this->option('days');

        if (is_numeric($days)) {
            return (int) $days;
        }

        return (int) config('retention.execution_draft_days');
    }
}
