<?php

namespace App\Actions\CodeTrackers;

use App\Enums\Ability;
use App\Enums\CodeTrackerType;
use App\Models\CodeTracker;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Proves the saved host answers, without posting anything.
 *
 * A 401 or 403 still counts as reached: the URL is good and auth is a later
 * concern. Connection failures and 5xx are not.
 */
final class TestCodeTrackerConnection
{
    /**
     * @return array{ok: bool, message: string}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, TestProject $project): array
    {
        Gate::forUser($user)->authorize(Ability::ManageCodeTrackers->value, $project);

        $tracker = $project->codeTracker;

        if ($tracker === null) {
            throw ValidationException::withMessages([
                'base_url' => 'Save a tracker before testing the connection.',
            ]);
        }

        $url = $this->probeUrl($tracker);

        try {
            $response = Http::connectTimeout(3)
                ->timeout(5)
                ->get($url);
        } catch (ConnectionException) {
            return [
                'ok' => false,
                'message' => __('Could not reach the tracker.'),
            ];
        }

        if ($response->serverError()) {
            return [
                'ok' => false,
                'message' => __('The tracker returned an error (:status).', [
                    'status' => $response->status(),
                ]),
            ];
        }

        return [
            'ok' => true,
            'message' => __('Reached the tracker (HTTP :status).', [
                'status' => $response->status(),
            ]),
        ];
    }

    private function probeUrl(CodeTracker $tracker): string
    {
        $base = rtrim($tracker->base_url, '/');
        $projectKey = $tracker->project_key;

        if ($projectKey === null || $projectKey === '') {
            return $base;
        }

        return match ($tracker->type) {
            CodeTrackerType::Github => $base.'/repos/'.$projectKey,
            CodeTrackerType::Gitlab => $base.'/api/v4/projects/'.rawurlencode($projectKey),
            CodeTrackerType::Bitbucket => $base.'/2.0/repositories/'.$projectKey,
            CodeTrackerType::Generic => $base,
        };
    }
}
