<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Authorization\RoleResolver;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\TestProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ask-AI reports shell. There is no LLM in the app; the page is the mockup UI.
 */
class CustomReportsController extends Controller
{
    public function index(
        Request $request,
        TestProject $testProject,
        RoleResolver $roleResolver,
    ): Response {
        $user = $this->actingUser($request);
        $gate = Gate::forUser($user);

        abort_unless(
            $gate->allows(Ability::ViewProjectMetrics->value, $testProject)
            || $gate->allows(Ability::ViewPlanMetrics->value, $testProject)
            || $roleResolver
                ->plansAllowing($user, Ability::ViewPlanMetrics, $testProject->testPlans()->get())
                ->isNotEmpty(),
            403,
        );

        return Inertia::render('reports/custom', [
            'project' => [
                'id' => $testProject->id,
                'name' => $testProject->name,
            ],
        ]);
    }
}
