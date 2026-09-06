<?php

namespace App\Http\Controllers\TestProjects;

use App\Actions\Authorization\RoleResolver;
use App\Actions\TestProjects\CreateTestProject;
use App\Actions\TestProjects\DeleteTestProject;
use App\Actions\TestProjects\UpdateTestProject;
use App\Concerns\PresentsAttachments;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\TestProjects\TestProjectDeleteRequest;
use App\Http\Requests\TestProjects\TestProjectStoreRequest;
use App\Http\Requests\TestProjects\TestProjectUpdateRequest;
use App\Models\TestProject;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The application's entry point: the projects a user can reach, and the
 * administration of those projects.
 *
 * Two audiences share this screen. Someone who merely works in projects sees
 * the active ones their effective role lets them open. Someone holding
 * `manage_test_projects` sees every project including deactivated ones, because
 * that ability already carries the power to create and delete any project, so
 * concealing one from the list would protect nothing.
 */
class TestProjectController extends Controller
{
    use PresentsAttachments;

    public function index(Request $request, RoleResolver $roleResolver): Response
    {
        $user = $this->actingUser($request);

        $projects = TestProject::query()
            ->withCount(['testSuites', 'testCases', 'testPlans'])
            ->orderBy('name')
            ->get();

        $openable = array_values(
            $roleResolver
                ->projectsAllowing($user, Ability::ViewTestCases, $projects)
                ->modelKeys(),
        );

        $canManage = Gate::forUser($user)->allows(Ability::ManageTestProjects->value);

        $listed = $canManage
            ? $projects
            : $projects->filter(
                fn (TestProject $project): bool => $project->is_active
                    && in_array($project->getKey(), $openable, true),
            );

        return Inertia::render('test-projects/index', [
            'projects' => $this->summaries($listed, $openable),
            'can' => ['manage' => $canManage],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::forUser($this->actingUser($request))->authorize(Ability::ManageTestProjects->value);

        return Inertia::render('test-projects/create');
    }

    public function store(
        TestProjectStoreRequest $request,
        CreateTestProject $createTestProject,
    ): RedirectResponse {
        $project = $createTestProject($this->actingUser($request), $request->projectAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test project created.')]);

        return to_route('specification.show', $project);
    }

    public function edit(Request $request, TestProject $testProject): Response
    {
        Gate::forUser($this->actingUser($request))->authorize(Ability::ManageTestProjects->value);

        return Inertia::render('test-projects/edit', [
            'project' => [
                'id' => $testProject->id,
                'name' => $testProject->name,
                'prefix' => $testProject->prefix,
                'description' => $testProject->description,
                'is_active' => $testProject->is_active,
                'is_public' => $testProject->is_public,
                'prefix_locked' => $testProject->test_case_counter > 0,
                'test_cases_count' => $testProject->testCases()->count(),
                'attachments' => $this->attachmentProps($testProject),
            ],
            'attachmentRules' => $this->attachmentRules(),
        ]);
    }

    public function update(
        TestProjectUpdateRequest $request,
        TestProject $testProject,
        UpdateTestProject $updateTestProject,
    ): RedirectResponse {
        $updateTestProject($this->actingUser($request), $testProject, $request->projectAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test project updated.')]);

        return to_route('projects.index');
    }

    public function destroy(
        TestProjectDeleteRequest $request,
        TestProject $testProject,
        DeleteTestProject $deleteTestProject,
    ): RedirectResponse {
        $deleteTestProject($this->actingUser($request), $testProject);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test project deleted.')]);

        return to_route('projects.index');
    }

    /**
     * @param  EloquentCollection<int, TestProject>  $projects
     * @param  list<int|string>  $openable
     * @return list<array<string, mixed>>
     */
    private function summaries(EloquentCollection $projects, array $openable): array
    {
        return array_values($projects->map(fn (TestProject $project): array => [
            'id' => $project->id,
            'name' => $project->name,
            'prefix' => $project->prefix,
            'description' => $project->description,
            'is_active' => $project->is_active,
            'is_public' => $project->is_public,
            'test_suites_count' => (int) $project->getAttribute('test_suites_count'),
            'test_cases_count' => (int) $project->getAttribute('test_cases_count'),
            'test_plans_count' => (int) $project->getAttribute('test_plans_count'),
            'can_open' => in_array($project->getKey(), $openable, true),
        ])->all());
    }
}
