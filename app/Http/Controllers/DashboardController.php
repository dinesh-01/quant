<?php

namespace App\Http\Controllers;

use App\Actions\Authorization\RoleResolver;
use App\Enums\Ability;
use App\Models\TestProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sends a signed-in user into a project Overview, or an empty workspace.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, RoleResolver $roleResolver): RedirectResponse|Response
    {
        $user = $this->actingUser($request);
        $projects = TestProject::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $reachable = Gate::forUser($user)->allows(Ability::ManageTestProjects->value)
            ? $projects
            : $roleResolver->projectsAllowing($user, Ability::ViewTestCases, $projects)
                ->merge($roleResolver->projectsAllowing($user, Ability::ExecuteTests, $projects))
                ->merge($roleResolver->projectsAllowing($user, Ability::ViewExecutions, $projects))
                ->merge($roleResolver->projectsAllowing($user, Ability::CreateTestPlans, $projects))
                ->unique('id')
                ->sortBy('name')
                ->values();

        if ($reachable->isEmpty()) {
            return Inertia::render('dashboard');
        }

        $lastId = (int) $request->cookie('quanta_last_project', '0');
        $chosen = $reachable->firstWhere('id', $lastId) ?? $reachable->first();

        return redirect()
            ->route('projects.show', $chosen)
            ->cookie('quanta_last_project', (string) $chosen->id, 60 * 24 * 365);
    }
}
