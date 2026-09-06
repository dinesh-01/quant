<?php

namespace App\Http\Controllers\Platforms;

use App\Actions\Platforms\CreatePlatform;
use App\Actions\Platforms\DeletePlatform;
use App\Actions\Platforms\UpdatePlatform;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platforms\PlatformStoreRequest;
use App\Http\Requests\Platforms\PlatformUpdateRequest;
use App\Models\Platform;
use App\Models\TestProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A project's platform vocabulary.
 *
 * Reading the list needs `view_platforms` and changing it needs
 * `manage_platforms`. Assigning those platforms to a plan is a different
 * ability and lives on the plan, not here.
 */
class PlatformController extends Controller
{
    public function index(Request $request, TestProject $testProject): Response
    {
        $user = $this->actingUser($request);

        Gate::forUser($user)->authorize(Ability::ViewPlatforms->value, $testProject);

        $platforms = $testProject->platforms()
            ->alphabetically()
            ->withCount(['testPlans', 'testCaseVersions', 'items'])
            ->get()
            ->map(fn (Platform $platform): array => $this->platformSummary($platform))
            ->all();

        return Inertia::render('platforms/index', [
            'project' => $this->projectProp($testProject),
            'platforms' => array_values($platforms),
            'can' => [
                'manage' => Gate::forUser($user)->allows(Ability::ManagePlatforms->value, $testProject),
            ],
        ]);
    }

    public function create(Request $request, TestProject $testProject): Response
    {
        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::ManagePlatforms->value, $testProject);

        return Inertia::render('platforms/create', [
            'project' => $this->projectProp($testProject),
        ]);
    }

    public function store(
        PlatformStoreRequest $request,
        TestProject $testProject,
        CreatePlatform $createPlatform,
    ): RedirectResponse {
        $createPlatform($this->actingUser($request), $testProject, $request->platform());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Platform created.')]);

        return to_route('platforms.index', $testProject);
    }

    public function edit(Request $request, Platform $platform): Response
    {
        $platform->loadMissing('testProject');

        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::ManagePlatforms->value, $platform->testProject);

        $platform->loadCount(['testPlans', 'testCaseVersions', 'items']);

        return Inertia::render('platforms/edit', [
            'project' => $this->projectProp($platform->testProject),
            'platform' => $this->platformSummary($platform),
        ]);
    }

    public function update(
        PlatformUpdateRequest $request,
        Platform $platform,
        UpdatePlatform $updatePlatform,
    ): RedirectResponse {
        $updatePlatform($this->actingUser($request), $platform, $request->platform());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Platform updated.')]);

        return to_route('platforms.index', $platform->testProject);
    }

    public function destroy(
        Request $request,
        Platform $platform,
        DeletePlatform $deletePlatform,
    ): RedirectResponse {
        $project = $platform->testProject;
        $name = $platform->name;

        $deletePlatform($this->actingUser($request), $platform);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('":name" deleted.', ['name' => $name]),
        ]);

        return to_route('platforms.index', $project);
    }

    /**
     * @return array{id: int, name: string}
     */
    private function projectProp(TestProject $project): array
    {
        return ['id' => $project->id, 'name' => $project->name];
    }

    /**
     * @return array{id: int, name: string, notes: string|null, enable_on_design: bool, enable_on_execution: bool, is_open: bool, test_plans_count: int, test_case_versions_count: int, plan_items_count: int}
     */
    private function platformSummary(Platform $platform): array
    {
        return [
            'id' => $platform->id,
            'name' => $platform->name,
            'notes' => $platform->notes,
            'enable_on_design' => $platform->enable_on_design,
            'enable_on_execution' => $platform->enable_on_execution,
            'is_open' => $platform->is_open,
            'test_plans_count' => (int) $platform->test_plans_count,
            'test_case_versions_count' => (int) $platform->test_case_versions_count,
            'plan_items_count' => (int) $platform->items_count,
        ];
    }
}
