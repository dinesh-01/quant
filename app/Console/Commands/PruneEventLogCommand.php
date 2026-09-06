<?php

namespace App\Console\Commands;

use App\Actions\Audit\PruneEventLog;
use App\Http\Requests\Audit\EventLogPruneRequest;
use Illuminate\Console\Command;

/**
 * Trims the audit trail to the configured retention. The same action the
 * event-log screen uses, without an actor — the scheduler has no session.
 */
class PruneEventLogCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:prune-event-log
                            {--days= : Days of trail to keep (defaults to retention.event_log_days)}';

    /**
     * @var string
     */
    protected $description = 'Discard audit records older than the retention window';

    public function handle(PruneEventLog $pruneEventLog): int
    {
        $keepDays = $this->keepDays();

        if ($keepDays < EventLogPruneRequest::MINIMUM_KEEP_DAYS) {
            $this->components->error(__(
                'Records from the last :min days cannot be discarded.',
                ['min' => EventLogPruneRequest::MINIMUM_KEEP_DAYS],
            ));

            return self::FAILURE;
        }

        $discarded = $pruneEventLog->unattended($keepDays);

        $this->components->info(__('Discarded :count audit :records older than :days days.', [
            'count' => $discarded,
            'records' => $discarded === 1 ? 'record' : 'records',
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

        return (int) config('retention.event_log_days');
    }
}
