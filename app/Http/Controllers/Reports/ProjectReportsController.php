<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Authorization\RoleResolver;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\TestCaseScriptLink;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Reports\ProjectDashboardReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The project's reports landing page.
 *
 * `view_project_metrics` sees every plan's latest-build status. Without it,
 * the page lists the plans the user may open with `view_plan_metrics`.
 */
class ProjectReportsController extends Controller
{
    public function index(
        Request $request,
        TestProject $testProject,
        RoleResolver $roleResolver,
        ProjectDashboardReport $projectDashboardReport,
    ): Response {
        $user = $this->actingUser($request);
        $gate = Gate::forUser($user);
        $canSeeDashboard = $gate->allows(Ability::ViewProjectMetrics->value, $testProject);

        $all = $testProject->testPlans()->orderBy('name')->get();
        $visible = $roleResolver->plansAllowing($user, Ability::ViewPlanMetrics, $all);

        abort_unless($canSeeDashboard || $visible->isNotEmpty(), 403);

        $cases = $testProject->testCases()->count();
        $automated = TestCaseScriptLink::query()
            ->whereHas(
                'testCaseVersion.testCase',
                fn ($query) => $query->where('test_project_id', $testProject->id),
            )
            ->distinct()
            ->count('test_case_version_id');

        return Inertia::render('reports/project', [
            'project' => [
                'id' => $testProject->id,
                'name' => $testProject->name,
            ],
            'can' => [
                'dashboard' => $canSeeDashboard,
            ],
            'case_stats' => [
                'total' => $cases,
                'automated' => $automated,
                'manual' => max(0, $cases - $automated),
            ],
            'plans' => $canSeeDashboard
                ? $projectDashboardReport($all)
                : $visible->map(fn (TestPlan $plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'items' => $plan->items()->count(),
                    'build' => null,
                    'counts' => null,
                ])->values()->all(),
        ]);
    }
}
