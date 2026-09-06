<?php

namespace App\Http\Controllers\CodeTrackers;

use App\Actions\CodeTrackers\SaveCodeTracker;
use App\Enums\Ability;
use App\Enums\CodeTrackerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CodeTrackers\CodeTrackerUpdateRequest;
use App\Models\TestProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The project's one code tracker.
 *
 * Reading needs `view_code_trackers` and saving needs `manage_code_trackers`.
 */
class CodeTrackerController extends Controller
{
    public function show(Request $request, TestProject $testProject): Response
    {
        $user = $this->actingUser($request);

        Gate::forUser($user)->authorize(Ability::ViewCodeTrackers->value, $testProject);

        $tracker = $testProject->codeTracker;

        return Inertia::render('code-trackers/show', [
            'project' => [
                'id' => $testProject->id,
                'name' => $testProject->name,
            ],
            'tracker' => $tracker === null ? null : [
                'name' => $tracker->name,
                'type' => $tracker->type->value,
                'base_url' => $tracker->base_url,
                'project_key' => $tracker->project_key,
                'view_url_template' => $tracker->view_url_template,
                'is_enabled' => $tracker->is_enabled,
            ],
            'types' => array_map(
                fn (CodeTrackerType $type): array => [
                    'value' => $type->value,
                    'label' => $type->name === 'Github' ? 'GitHub' : ucfirst($type->value),
                ],
                CodeTrackerType::cases(),
            ),
            'can' => [
                'manage' => Gate::forUser($user)->allows(Ability::ManageCodeTrackers->value, $testProject),
            ],
        ]);
    }

    public function update(
        CodeTrackerUpdateRequest $request,
        TestProject $testProject,
        SaveCodeTracker $saveCodeTracker,
    ): RedirectResponse {
        $saveCodeTracker($this->actingUser($request), $testProject, $request->tracker());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Code tracker saved.')]);

        return to_route('code-trackers.show', $testProject);
    }
}
