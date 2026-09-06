<?php

namespace App\Http\Middleware;

use App\Actions\Authorization\RoleResolver;
use App\Enums\Ability;
use App\Models\Build;
use App\Models\CodeTracker;
use App\Models\Execution;
use App\Models\ExecutionIssue;
use App\Models\IssueTracker;
use App\Models\Milestone;
use App\Models\Platform;
use App\Models\Requirement;
use App\Models\RequirementCoverage;
use App\Models\RequirementMonitor;
use App\Models\RequirementSpec;
use App\Models\RequirementVersion;
use App\Models\TestCaseRelation;
use App\Models\TestCaseScriptLink;
use App\Models\TestCaseVersion;
use App\Models\TesterAssignment;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                /**
                 * Application-wide abilities, as distinct from the
                 * project-scoped ones on `currentProject`. Named under `auth`
                 * rather than as a top-level `can` so it cannot be shadowed by
                 * a page's own `can` prop.
                 */
                'can' => fn (): array => [
                    'manageUsers' => Gate::allows(Ability::ManageUsers->value),
                    'manageRoles' => Gate::allows(Ability::ManageRoles->value),
                    'viewEventLog' => Gate::allows(Ability::ViewEventLog->value),
                    'viewCustomFields' => Gate::allows(Ability::ViewCustomFields->value),
                ],
            ],
            'currentProject' => fn (): ?array => $this->currentProject($request, app(RoleResolver::class)),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The project the current route is scoped to, if any.
     *
     * Read from the route binding rather than from the session. Slice C put the
     * project in every specification URL so that nodes are linkable, which
     * means the URL is already the single source of truth for "which project am
     * I in". Mirroring it in the session would create a second answer that can
     * disagree with the first — the bug where opening a link in a new tab
     * silently changes what another tab is looking at.
     *
     * The abilities travel with it so the sidebar can leave out destinations
     * the user would only be refused at. All of them resolve from a role that
     * is already loaded.
     *
     * @return array{id: int, name: string, prefix: string, can: array{viewSpecification: bool, viewRequirements: bool, manageTestPlans: bool, viewKeywords: bool, viewPlatforms: bool, assignCustomFields: bool, manageMembers: bool, selectPlans: bool, viewReports: bool, viewCodeTrackers: bool, viewIssueTrackers: bool}}|null
     */
    private function currentProject(Request $request, RoleResolver $roleResolver): ?array
    {
        $project = $this->projectFromRoute($request);
        $user = $request->user();

        if (! $project instanceof TestProject || ! $user instanceof User) {
            return null;
        }

        $gate = Gate::forUser($user);

        return [
            'id' => $project->id,
            'name' => $project->name,
            'prefix' => $project->prefix,
            'can' => [
                'viewSpecification' => $gate->allows(Ability::ViewTestCases->value, $project),
                'viewRequirements' => $gate->allows(Ability::ViewRequirements->value, $project),
                'manageTestPlans' => $gate->allows(Ability::CreateTestPlans->value, $project),
                'viewKeywords' => $gate->allows(Ability::ViewKeywords->value, $project),
                'viewPlatforms' => $gate->allows(Ability::ViewPlatforms->value, $project),
                'assignCustomFields' => $gate->allows(Ability::AssignCustomFields->value, $project),
                'manageMembers' => $roleResolver->mayAssignRolesIn($user, $project),
                'selectPlans' => $this->canSelectPlans($user, $project, $gate),
                'viewReports' => $this->canViewReports($user, $project, $gate, $roleResolver),
                'viewCodeTrackers' => $gate->allows(Ability::ViewCodeTrackers->value, $project),
                'viewIssueTrackers' => $gate->allows(Ability::ViewIssueTrackers->value, $project),
            ],
        ];
    }

    /**
     * The project this request is in, whether the URL named it or named
     * something that already belongs to one.
     *
     * Plan, platform, build, item, execution and requirement routes bind
     * those models alone so a nested project id cannot disagree with them.
     * The sidebar still needs the project, so walk from whichever was bound.
     */
    private function projectFromRoute(Request $request): ?TestProject
    {
        $parameter = $request->route()?->parameter('testProject');

        if ($parameter instanceof TestProject) {
            return $parameter;
        }

        $plan = $request->route()?->parameter('testPlan');

        if ($plan instanceof TestPlan) {
            $plan->loadMissing('testProject');

            return $plan->testProject;
        }

        $platform = $request->route()?->parameter('platform');

        if ($platform instanceof Platform) {
            $platform->loadMissing('testProject');

            return $platform->testProject;
        }

        $milestone = $request->route()?->parameter('milestone');

        if ($milestone instanceof Milestone) {
            $milestone->loadMissing('testPlan.testProject');

            return $milestone->testPlan->testProject;
        }

        $build = $request->route()?->parameter('build');

        if ($build instanceof Build) {
            $build->loadMissing('testPlan.testProject');

            return $build->testPlan->testProject;
        }

        $item = $request->route()?->parameter('testPlanItem');

        if ($item instanceof TestPlanItem) {
            $item->loadMissing('testPlan.testProject');

            return $item->testPlan->testProject;
        }

        $assignment = $request->route()?->parameter('testerAssignment');

        if ($assignment instanceof TesterAssignment) {
            $assignment->loadMissing('testPlanItem.testPlan.testProject');

            return $assignment->testPlanItem->testPlan->testProject;
        }

        $version = $request->route()?->parameter('testCaseVersion');

        if ($version instanceof TestCaseVersion) {
            $version->loadMissing('testCase.testProject');

            return $version->testCase->testProject;
        }

        $relation = $request->route()?->parameter('testCaseRelation');

        if ($relation instanceof TestCaseRelation) {
            $relation->loadMissing('source.testProject');

            return $relation->source->testProject;
        }

        $spec = $request->route()?->parameter('requirementSpec');

        if ($spec instanceof RequirementSpec) {
            $spec->loadMissing('testProject');

            return $spec->testProject;
        }

        $monitor = $request->route()?->parameter('requirementMonitor');

        if ($monitor instanceof RequirementMonitor) {
            $monitor->loadMissing('requirement.testProject');

            return $monitor->requirement->testProject;
        }

        $requirement = $request->route()?->parameter('requirement');

        if ($requirement instanceof Requirement) {
            $requirement->loadMissing('testProject');

            return $requirement->testProject;
        }

        $requirementVersion = $request->route()?->parameter('requirementVersion');

        if ($requirementVersion instanceof RequirementVersion) {
            $requirementVersion->loadMissing('requirement.testProject');

            return $requirementVersion->requirement->testProject;
        }

        $coverage = $request->route()?->parameter('requirementCoverage');

        if ($coverage instanceof RequirementCoverage) {
            $coverage->loadMissing('requirementVersion.requirement.testProject');

            return $coverage->requirementVersion->requirement->testProject;
        }

        $execution = $request->route()?->parameter('execution');

        if ($execution instanceof Execution) {
            $execution->loadMissing('testPlan.testProject');

            return $execution->testPlan->testProject;
        }

        $issue = $request->route()?->parameter('executionIssue');

        if ($issue instanceof ExecutionIssue) {
            $issue->loadMissing('execution.testPlan.testProject');

            return $issue->execution->testPlan->testProject;
        }

        $tracker = $request->route()?->parameter('codeTracker');

        if ($tracker instanceof CodeTracker) {
            $tracker->loadMissing('testProject');

            return $tracker->testProject;
        }

        $issueTracker = $request->route()?->parameter('issueTracker');

        if ($issueTracker instanceof IssueTracker) {
            $issueTracker->loadMissing('testProject');

            return $issueTracker->testProject;
        }

        $script = $request->route()?->parameter('testCaseScriptLink');

        if ($script instanceof TestCaseScriptLink) {
            $script->loadMissing('testCaseVersion.testCase.testProject');

            return $script->testCaseVersion->testCase->testProject;
        }

        return null;
    }

    /**
     * Testers reach their plans here, not through the management list.
     *
     * A project-level execute or view-executions grant is enough. A plan-only
     * role is also enough: that is the person the selector exists for. The
     * page itself still filters to plans they can actually run or inspect.
     */
    private function canSelectPlans(User $user, TestProject $project, GateContract $gate): bool
    {
        if (
            $gate->allows(Ability::ExecuteTests->value, $project)
            || $gate->allows(Ability::ViewExecutions->value, $project)
        ) {
            return true;
        }

        return $user->planRoles()
            ->whereIn('test_plan_id', $project->testPlans()->select('id'))
            ->exists();
    }

    /**
     * Plan-only testers reach reports through a plan role, not a project grant.
     */
    private function canViewReports(
        User $user,
        TestProject $project,
        GateContract $gate,
        RoleResolver $roleResolver,
    ): bool {
        if (
            $gate->allows(Ability::ViewProjectMetrics->value, $project)
            || $gate->allows(Ability::ViewPlanMetrics->value, $project)
        ) {
            return true;
        }

        return $roleResolver
            ->plansAllowing($user, Ability::ViewPlanMetrics, $project->testPlans()->get())
            ->isNotEmpty();
    }
}
