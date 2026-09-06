<?php

namespace App\Actions\CodeTrackers;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\CodeTrackerType;
use App\Models\CodeTracker;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Creates or updates the project's one code tracker.
 */
final class SaveCodeTracker
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, type: CodeTrackerType, base_url: string, project_key: string|null, view_url_template: string|null, is_enabled: bool}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestProject $project, array $attributes): CodeTracker
    {
        Gate::forUser($user)->authorize(Ability::ManageCodeTrackers->value, $project);

        $tracker = $project->codeTracker ?? new CodeTracker;
        $tracker->fill($attributes);
        $tracker->testProject()->associate($project);

        $properties = $this->audit->changes($tracker);

        $tracker->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::CodeTrackerSaved, $user, $project, $properties);
        }

        return $tracker;
    }
}
