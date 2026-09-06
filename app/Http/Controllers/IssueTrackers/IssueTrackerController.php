<?php

namespace App\Http\Controllers\IssueTrackers;

use App\Actions\IssueTrackers\SaveIssueTracker;
use App\Enums\Ability;
use App\Enums\IssueTrackerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\IssueTrackers\IssueTrackerUpdateRequest;
use App\Models\TestProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The project's one issue tracker.
 *
 * Reading needs `view_issue_trackers` and saving needs `manage_issue_trackers`.
 */
class IssueTrackerController extends Controller
{
    public function show(Request $request, TestProject $testProject): Response
    {
        $user = $this->actingUser($request);

        Gate::forUser($user)->authorize(Ability::ViewIssueTrackers->value, $testProject);

        $tracker = $testProject->issueTracker;

        return Inertia::render('issue-trackers/show', [
            'project' => [
                'id' => $testProject->id,
                'name' => $testProject->name,
            ],
            'tracker' => $tracker === null ? null : [
                'name' => $tracker->name,
                'type' => $tracker->type->value,
                'base_url' => $tracker->base_url,
                'project_key' => $tracker->setting('project_key'),
                'issue_type' => $tracker->setting('issue_type', 'Bug'),
                'email' => $tracker->setting('email'),
                'has_api_token' => $tracker->hasApiToken(),
                'proxy' => $tracker->setting('proxy'),
                'is_enabled' => $tracker->is_enabled,
            ],
            'types' => array_map(
                fn (IssueTrackerType $type): array => [
                    'value' => $type->value,
                    'label' => 'Jira',
                ],
                IssueTrackerType::cases(),
            ),
            'can' => [
                'manage' => Gate::forUser($user)->allows(Ability::ManageIssueTrackers->value, $testProject),
            ],
        ]);
    }

    public function update(
        IssueTrackerUpdateRequest $request,
        TestProject $testProject,
        SaveIssueTracker $saveIssueTracker,
    ): RedirectResponse {
        $saveIssueTracker($this->actingUser($request), $testProject, $request->tracker());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue tracker saved.')]);

        return to_route('issue-trackers.show', $testProject);
    }
}
