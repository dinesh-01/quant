<?php

namespace App\Http\Controllers\TestProjects;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\TestProject;
use App\Reports\ProjectOverviewReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The project home that matches the mockup Overview.
 */
class ProjectOverviewController extends Controller
{
    public function show(
        Request $request,
        TestProject $testProject,
        ProjectOverviewReport $overview,
    ): Response {
        $user = $this->actingUser($request);
        $gate = Gate::forUser($user);

        abort_unless(
            $gate->allows(Ability::ViewTestCases->value, $testProject)
            || $gate->allows(Ability::ExecuteTests->value, $testProject)
            || $gate->allows(Ability::ViewExecutions->value, $testProject)
            || $gate->allows(Ability::CreateTestPlans->value, $testProject)
            || $gate->allows(Ability::ViewProjectMetrics->value, $testProject)
            || $gate->allows(Ability::ManageTestProjects->value),
            403,
        );

        return Inertia::render('overview/show', [
            'project' => [
                'id' => $testProject->id,
                'name' => $testProject->name,
                'prefix' => $testProject->prefix,
            ],
            'overview' => $overview($testProject, $user),
            'viewer' => $user->name,
        ]);
    }
}
