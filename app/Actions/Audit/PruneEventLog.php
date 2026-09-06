<?php

namespace App\Actions\Audit;

use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Discards audit records older than a given age.
 *
 * The trail grows without bound and nothing else ever deletes from it, so
 * there has to be a way to trim it — but it is the one write in the
 * application whose purpose is to destroy evidence, which is why it sits
 * behind its own ability rather than under `view_event_log`.
 *
 * Trimming by age rather than offering "clear the log": a cutoff states a
 * retention policy, and someone covering their tracks cannot use it to remove
 * this morning's events.
 */
final class PruneEventLog
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return int how many records were discarded
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, int $keepDays): int
    {
        Gate::forUser($actor)->authorize(Ability::ManageEventLog->value);

        return $this->discardOlderThan($keepDays, $actor);
    }

    /**
     * Scheduler / artisan. No actor and no gate: shell access is the privilege,
     * the same judgement `app:make-administrator` is recorded under.
     *
     * @return int how many records were discarded
     */
    public function unattended(int $keepDays): int
    {
        return $this->discardOlderThan($keepDays, null);
    }

    /**
     * @return int how many records were discarded
     */
    private function discardOlderThan(int $keepDays, ?User $actor): int
    {
        $cutoff = now()->subDays($keepDays)->startOfDay();

        $discarded = AuditEvent::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        /**
         * Recorded afterwards, so the record of the pruning is not itself
         * inside the range being pruned. Without this the trail would have a
         * silent gap, which is indistinguishable from tampering.
         */
        $this->audit->record(AuditAction::EventLogPruned, $actor, null, [
            'keep_days' => $keepDays,
            'before' => $cutoff->toDateTimeString(),
            'discarded' => $discarded,
        ]);

        return $discarded;
    }
}
