<?php

namespace App\Actions\IssueTrackers;

use App\Enums\Ability;
use App\IssueTrackers\AdapterFactory;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Proves the saved host accepts these credentials.
 *
 * Unlike a code-tracker probe, 401 and 403 fail: creating issues needs a
 * working token, so "the URL answered" is not enough.
 */
final class TestIssueTrackerConnection
{
    public function __construct(private readonly AdapterFactory $adapters) {}

    /**
     * @return array{ok: bool, message: string}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, TestProject $project): array
    {
        Gate::forUser($user)->authorize(Ability::ManageIssueTrackers->value, $project);

        $tracker = $project->issueTracker;

        if ($tracker === null) {
            throw ValidationException::withMessages([
                'base_url' => 'Save a tracker before testing the connection.',
            ]);
        }

        if (! $tracker->hasApiToken()) {
            throw ValidationException::withMessages([
                'api_token' => 'Save an API token before testing the connection.',
            ]);
        }

        return $this->adapters->make($tracker)->testConnection();
    }
}
