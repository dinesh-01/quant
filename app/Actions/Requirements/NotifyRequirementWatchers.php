<?php

namespace App\Actions\Requirements;

use App\Models\Requirement;
use App\Models\User;
use App\Notifications\RequirementChangedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Tells watchers that a requirement's wording changed.
 *
 * Internal collaborator of the version write actions. Does not authorize.
 */
final class NotifyRequirementWatchers
{
    public function __invoke(Requirement $requirement, User $actor, string $reason, bool $deleted = false): void
    {
        $requirement->loadMissing('monitors.user', 'testProject');

        $watchers = $requirement->monitors
            ->map(fn ($monitor) => $monitor->user)
            ->filter(fn (?User $user): bool => $user instanceof User && $user->is($actor) === false);

        if ($watchers->isEmpty()) {
            return;
        }

        Notification::send($watchers, new RequirementChangedNotification(
            requirementId: $requirement->id,
            projectId: $requirement->test_project_id,
            docId: $requirement->doc_id,
            name: $requirement->name,
            actorName: $actor->name,
            reason: $reason,
            deleted: $deleted,
        ));
    }
}
