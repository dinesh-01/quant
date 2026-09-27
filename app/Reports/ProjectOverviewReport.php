<?php

namespace App\Reports;

use App\Enums\ExecutionStatus;
use App\Enums\TestPlanStatus;
use App\Models\Build;
use App\Models\Execution;
use App\Models\TestCaseScriptLink;
use App\Models\TesterAssignment;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The figures the project Overview screen needs.
 */
final class ProjectOverviewReport
{
    public function __construct(
        private readonly ProjectDashboardReport $projectDashboardReport,
        private readonly PlanStatusReport $planStatusReport,
        private readonly ProjectActivityFeed $projectActivityFeed,
    ) {}

    /**
     * @return array{
     *     greeting: string,
     *     cases: int,
     *     cases_this_week: int,
     *     automated: int,
     *     active_runs: int,
     *     runs_in_progress: int,
     *     runs_blocked: int,
     *     pass_rate: int|null,
     *     previous_build: array{name: string, delta: int}|null,
     *     latest_build: array{name: string, counts: array{passed: int, failed: int, blocked: int, not_run: int}, total: int}|null,
     *     runs: list<array<string, mixed>>,
     *     needs_attention: list<array<string, mixed>>,
     *     activity: list<array<string, mixed>>
     * }
     */
    public function __invoke(TestProject $project, ?User $viewer = null): array
    {
        $plans = $project->testPlans()
            ->where('status', TestPlanStatus::Active)
            ->orderBy('id')
            ->get()
            ->each(fn (TestPlan $plan) => $plan->setRelation('testProject', $project));

        /** Ordered by plan, not by name, so the list reads in the order plans were opened. */
        $rows = collect(($this->projectDashboardReport)($plans))
            ->sortBy('id')
            ->values()
            ->all();

        $cases = $project->testCases()->count();
        $casesThisWeek = $project->testCases()
            ->where('created_at', '>=', Carbon::now()->subWeek())
            ->count();
        $automated = TestCaseScriptLink::query()
            ->whereHas(
                'testCaseVersion.testCase',
                fn ($query) => $query->where('test_project_id', $project->id),
            )
            ->distinct()
            ->count('test_case_version_id');

        /**
         * A run is active while it still has cases nobody has executed on the
         * current build. A run whose every case has a verdict is finished, even
         * when some of those verdicts are failures — it needs a fix, not a
         * tester, so it belongs on Needs attention rather than in this list.
         */
        $active = collect($rows)->filter(
            fn (array $row): bool => $row['counts'] !== null && $row['counts']['not_run'] > 0,
        );

        $latest = collect($rows)
            ->filter(fn (array $row): bool => $row['build'] !== null && $row['counts'] !== null)
            ->sortByDesc(fn (array $row): int => $row['build']['id'] ?? 0)
            ->first();

        $passRate = $latest === null ? null : $this->passRate($latest['counts']);

        $previousBuild = $this->previousBuildComparison($plans, $latest, $passRate);

        $buildIds = collect($rows)
            ->pluck('build.id')
            ->filter()
            ->all();

        $assigneesByBuild = $buildIds === []
            ? collect()
            : TesterAssignment::query()
                ->whereIn('build_id', $buildIds)
                ->with('user:id,name')
                ->orderBy('id')
                ->get()
                ->groupBy('build_id')
                ->map(fn ($group) => $group->pluck('user.name')->filter()->unique()->values()->all());

        $testersByBuild = $buildIds === []
            ? collect()
            : Execution::query()
                ->whereIn('build_id', $buildIds)
                ->where('is_draft', false)
                ->whereNotNull('tester_id')
                ->select('build_id', 'tester_id')
                ->distinct()
                ->orderBy('tester_id')
                ->with('tester:id,name')
                ->get()
                ->groupBy('build_id')
                ->map(fn ($group) => $group->pluck('tester.name')->filter()->unique()->values()->all());

        /**
         * A run counts as blocked when blockers outnumber failures: the tester
         * is held up by the environment rather than working through defects.
         */
        $runsBlocked = $active->filter(
            fn (array $row): bool => $row['counts']['blocked'] > $row['counts']['failed'],
        )->count();
        $runsInProgress = $active->count() - $runsBlocked;

        /** Only the build each active run is on — an old build's failures are not current. */
        $needsAttention = $buildIds === []
            ? collect()
            : Execution::query()
                ->with(['testPlan', 'testCaseVersion.testCase', 'tester', 'issues'])
                ->where('is_draft', false)
                ->whereIn('status', [ExecutionStatus::Failed, ExecutionStatus::Blocked])
                ->whereIn('build_id', $buildIds)
                ->orderByDesc('id')
                ->limit(4)
                ->get();

        $needsAttention = $needsAttention
            ->map(fn (Execution $execution): array => [
                'id' => $execution->id,
                'name' => $execution->testCaseVersion->testCase->name,
                'external_id' => $execution->testCaseVersion->testCase->fullExternalId(),
                'plan' => $execution->testPlan->name,
                'plan_id' => $execution->test_plan_id,
                'item_id' => $execution->test_plan_item_id,
                'status' => $execution->status->value,
                'priority' => $execution->testCaseVersion->importance->value,
                'tester' => $execution->tester?->name,
                'issue' => $execution->issues->first()?->issue_id,
                'issue_url' => $execution->issues->first()?->issue_url,
                'issue_note' => $execution->issues->first()?->issue_summary ?? $execution->notes,
            ])
            ->all();

        $activity = ($this->projectActivityFeed)($project, $viewer);

        $hour = (int) Carbon::now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

        return [
            'greeting' => $greeting,
            'cases' => $cases,
            'cases_this_week' => $casesThisWeek,
            'automated' => $automated,
            'active_runs' => $active->count(),
            'runs_in_progress' => $runsInProgress,
            'runs_blocked' => $runsBlocked,
            'pass_rate' => $passRate,
            'previous_build' => $previousBuild,
            'latest_build' => $latest === null || $latest['counts'] === null
                ? null
                : [
                    'name' => $latest['build']['name'] ?? 'Latest build',
                    'counts' => $latest['counts'],
                    'total' => $latest['items'],
                ],
            'runs' => $active->map(function (array $row) use ($assigneesByBuild, $plans, $testersByBuild): array {
                $buildId = $row['build']['id'] ?? null;
                $assignees = $buildId === null
                    ? []
                    : ($assigneesByBuild->get($buildId) ?: $testersByBuild->get($buildId) ?: []);

                return [
                    'id' => $row['id'],
                    'external_id' => $plans->firstWhere('id', $row['id'])?->fullExternalId(),
                    'name' => $row['name'],
                    'build' => $row['build']['name'] ?? null,
                    'items' => $row['items'],
                    'counts' => $row['counts'],
                    'assignees' => array_values($assignees),
                ];
            })->values()->all(),
            'needs_attention' => $needsAttention,
            'activity' => $activity,
        ];
    }

    /**
     * @param  Collection<int, TestPlan>  $plans
     * @param  array<string, mixed>|null  $latest
     * @return array{name: string, delta: int}|null
     */
    private function previousBuildComparison($plans, ?array $latest, ?int $passRate): ?array
    {
        if ($latest === null || $passRate === null) {
            return null;
        }

        $plan = $plans->firstWhere('id', $latest['id']);
        $previous = $plan?->builds->sortByDesc('id')->values()->get(1);

        if (! $plan instanceof TestPlan || ! $previous instanceof Build) {
            return null;
        }

        $previousRate = $this->passRate(($this->planStatusReport)($plan, $previous)['counts']);

        if ($previousRate === null) {
            return null;
        }

        return [
            'name' => $previous->name,
            'delta' => $passRate - $previousRate,
        ];
    }

    /**
     * The share of executed cases that passed.
     *
     * Cases nobody has run yet are left out: they are not failures, and folding
     * them in would make a run's pass rate climb as it progresses even though
     * no verdict changed.
     *
     * @param  array{passed: int, failed: int, blocked: int, not_run: int}  $counts
     */
    private function passRate(array $counts): ?int
    {
        $executed = $counts['passed'] + $counts['failed'] + $counts['blocked'];

        return $executed === 0 ? null : (int) round(($counts['passed'] / $executed) * 100);
    }
}
