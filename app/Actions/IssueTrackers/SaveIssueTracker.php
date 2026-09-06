<?php

namespace App\Actions\IssueTrackers;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\IssueTrackerType;
use App\Models\IssueTracker;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Creates or updates the project's one issue tracker.
 *
 * An empty API token on update keeps the stored token. The token itself is
 * never written to the audit trail — `settings` is hidden on the model.
 */
final class SaveIssueTracker
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, type: IssueTrackerType, base_url: string, is_enabled: bool, project_key: string, issue_type: string, email: string, api_token: string|null, proxy: string|null}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestProject $project, array $attributes): IssueTracker
    {
        Gate::forUser($user)->authorize(Ability::ManageIssueTrackers->value, $project);

        $tracker = $project->issueTracker ?? new IssueTracker;
        $existing = $tracker->settings ?? [];
        $token = $attributes['api_token'] ?? '';

        if ($token === '' && filled($existing['api_token'] ?? null)) {
            $token = $existing['api_token'];
        }

        $tracker->fill([
            'name' => $attributes['name'],
            'type' => $attributes['type'],
            'base_url' => $attributes['base_url'],
            'is_enabled' => $attributes['is_enabled'],
        ]);
        $tracker->settings = [
            'project_key' => $attributes['project_key'],
            'issue_type' => $attributes['issue_type'],
            'email' => $attributes['email'],
            'api_token' => $token,
            'proxy' => $attributes['proxy'],
        ];
        $tracker->testProject()->associate($project);

        $properties = $this->audit->changes($tracker);

        $tracker->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::IssueTrackerSaved, $user, $project, $properties);
        }

        return $tracker;
    }
}
