<?php

namespace App\Http\Controllers\Executions;

use App\Actions\IssueTrackers\RefreshIssueStatuses;
use App\Actions\TestSpecification\ExpandGhostMarkup;
use App\Concerns\PresentsAttachments;
use App\Concerns\PresentsCustomFields;
use App\Enums\Ability;
use App\Enums\ExecutionStatus;
use App\Http\Controllers\Controller;
use App\Models\Build;
use App\Models\Execution;
use App\Models\ExecutionIssue;
use App\Models\TesterAssignment;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The run list for a plan, and the run pane for one item.
 */
class ExecutionNavigatorController extends Controller
{
    use PresentsAttachments;
    use PresentsCustomFields;

    /**
     * @throws AuthorizationException
     */
    public function index(Request $request, TestPlan $testPlan): Response
    {
        $user = $this->actingUser($request);
        $this->authorizeView($user, $testPlan);

        $testPlan->loadMissing('testProject');
        $build = $this->selectedBuild($request, $testPlan);
        $assignedOnly = Gate::forUser($user)->allows(Ability::ExecuteOnlyAssignedTestCases->value, $testPlan);

        $items = $testPlan->items()
            ->with(['testCaseVersion.testCase.testProject', 'testCaseVersion.steps', 'platform'])
            ->get();

        if ($assignedOnly && $build !== null) {
            $assignedIds = TesterAssignment::query()
                ->where('build_id', $build->id)
                ->where('user_id', $user->id)
                ->pluck('test_plan_item_id');
            $items = $items->whereIn('id', $assignedIds)->values();
        }

        $latest = $build === null
            ? collect()
            : Execution::query()
                ->where('build_id', $build->id)
                ->whereIn('test_plan_item_id', $items->modelKeys())
                ->where('is_draft', false)
                ->orderByDesc('id')
                ->get()
                ->unique('test_plan_item_id')
                ->keyBy('test_plan_item_id');

        return Inertia::render('executions/index', [
            'project' => [
                'id' => $testPlan->testProject->id,
                'name' => $testPlan->testProject->name,
            ],
            'plan' => [
                'id' => $testPlan->id,
                'name' => $testPlan->name,
                'is_open' => $testPlan->is_open,
            ],
            'builds' => $testPlan->builds()
                ->get()
                ->map(fn (Build $each): array => [
                    'id' => $each->id,
                    'name' => $each->name,
                    'is_open' => $each->is_open,
                    'is_active' => $each->is_active,
                ])
                ->all(),
            'selectedBuildId' => $build?->id,
            'can' => [
                'execute' => Gate::forUser($user)->allows(Ability::ExecuteTests->value, $testPlan),
            ],
            'items' => $items->map(function (TestPlanItem $item) use ($latest): array {
                $run = $latest->get($item->id);

                return [
                    'id' => $item->id,
                    'full_external_id' => $item->testCaseVersion->testCase->fullExternalId(),
                    'name' => $item->testCaseVersion->testCase->name,
                    'version' => $item->testCaseVersion->version,
                    'platform' => $item->platform?->name,
                    'latest_status' => $run?->status->value,
                ];
            })->values()->all(),
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    public function show(
        Request $request,
        TestPlan $testPlan,
        TestPlanItem $testPlanItem,
        ExpandGhostMarkup $expandGhostMarkup,
        RefreshIssueStatuses $refreshIssueStatuses,
    ): Response {
        $user = $this->actingUser($request);
        $this->authorizeView($user, $testPlan);

        abort_unless($testPlanItem->test_plan_id === $testPlan->id, 404);

        $testPlan->loadMissing('testProject.issueTracker');
        $testPlanItem->load([
            'testCaseVersion.testCase.testProject',
            'testCaseVersion.steps',
            'platform',
        ]);

        $build = $this->selectedBuild($request, $testPlan);
        abort_if($build === null, 404);

        $draft = Execution::query()
            ->with(['steps', 'issues'])
            ->where('test_plan_item_id', $testPlanItem->id)
            ->where('build_id', $build->id)
            ->where('tester_id', $user->id)
            ->where('is_draft', true)
            ->first();

        $history = Execution::query()
            ->with(['tester', 'issues'])
            ->where('test_plan_item_id', $testPlanItem->id)
            ->where('build_id', $build->id)
            ->where('is_draft', false)
            ->orderByDesc('executed_at')
            ->orderByDesc('id')
            ->get();

        $refreshIssueStatuses(
            $testPlan->testProject,
            $history->flatMap(fn (Execution $execution) => $execution->issues),
        );

        $gate = Gate::forUser($user);
        $tracker = $testPlan->testProject->issueTracker;

        $subject = $draft ?? tap(new Execution, function (Execution $execution) use ($testPlan): void {
            $execution->test_plan_id = $testPlan->id;
            $execution->setRelation('testPlan', $testPlan);
        });

        return Inertia::render('executions/show', [
            'project' => [
                'id' => $testPlan->testProject->id,
                'name' => $testPlan->testProject->name,
            ],
            'plan' => [
                'id' => $testPlan->id,
                'name' => $testPlan->name,
                'is_open' => $testPlan->is_open,
            ],
            'build' => [
                'id' => $build->id,
                'name' => $build->name,
                'is_open' => $build->is_open,
            ],
            'item' => [
                'id' => $testPlanItem->id,
                'full_external_id' => $testPlanItem->testCaseVersion->testCase->fullExternalId(),
                'name' => $testPlanItem->testCaseVersion->testCase->name,
                'version' => $testPlanItem->testCaseVersion->version,
                'summary' => $expandGhostMarkup(
                    $testPlanItem->testCaseVersion->summary,
                    $testPlan->testProject,
                    'summary',
                ),
                'preconditions' => $expandGhostMarkup(
                    $testPlanItem->testCaseVersion->preconditions,
                    $testPlan->testProject,
                    'preconditions',
                ),
                'platform' => $testPlanItem->platform?->name,
                'steps' => $testPlanItem->testCaseVersion->steps->map(fn ($step): array => [
                    'id' => $step->id,
                    'sort_order' => $step->sort_order,
                    'actions' => $expandGhostMarkup(
                        $step->actions,
                        $testPlan->testProject,
                        'actions',
                    ),
                    'expected_results' => $expandGhostMarkup(
                        $step->expected_results,
                        $testPlan->testProject,
                        'expected_results',
                    ),
                ])->all(),
            ],
            'customFields' => $this->customFieldProps($subject, onExecution: true),
            'attachments' => $draft === null ? [] : $this->attachmentProps($draft),
            'attachmentRules' => $this->attachmentRules(),
            'draftId' => $draft?->id,
            'draft' => $draft === null ? null : $this->executionProp($draft),
            'history' => $history->map(fn (Execution $execution): array => $this->executionProp($execution))->all(),
            'statuses' => array_map(fn (ExecutionStatus $status): array => [
                'value' => $status->value,
                'label' => $status->name,
            ], ExecutionStatus::cases()),
            'issueTracker' => [
                'enabled' => $tracker !== null && $tracker->isUsable(),
                'name' => $tracker?->name,
            ],
            'can' => [
                'execute' => $gate->allows(Ability::ExecuteTests->value, $testPlan)
                    && $testPlan->is_open
                    && $build->is_open,
                'delete' => $gate->allows(Ability::DeleteExecutions->value, $testPlan),
                'editNotes' => $gate->allows(Ability::EditExecutionNotes->value, $testPlan),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function executionProp(Execution $execution): array
    {
        return [
            'id' => $execution->id,
            'status' => $execution->status->value,
            'notes' => $execution->notes,
            'duration' => $execution->duration,
            'is_draft' => $execution->is_draft,
            'version' => $execution->version,
            'tester' => $execution->tester?->name,
            'executed_at' => $execution->executed_at?->toIso8601String(),
            'steps' => $execution->relationLoaded('steps')
                ? $execution->steps->map(fn ($step): array => [
                    'id' => $step->id,
                    'test_case_step_id' => $step->test_case_step_id,
                    'status' => $step->status->value,
                    'notes' => $step->notes,
                ])->all()
                : [],
            'issues' => $execution->relationLoaded('issues')
                ? $execution->issues->map(fn (ExecutionIssue $issue): array => [
                    'id' => $issue->id,
                    'issue_id' => $issue->issue_id,
                    'issue_url' => $issue->issue_url,
                    'issue_status' => $issue->issue_status,
                    'issue_summary' => $issue->issue_summary,
                ])->all()
                : [],
        ];
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeView(User $user, TestPlan $plan): void
    {
        $gate = Gate::forUser($user);

        if (
            $gate->allows(Ability::ExecuteTests->value, $plan)
            || $gate->allows(Ability::ViewExecutions->value, $plan)
        ) {
            return;
        }

        throw new AuthorizationException;
    }

    private function selectedBuild(Request $request, TestPlan $plan): ?Build
    {
        if ($request->filled('build')) {
            return $plan->builds()->whereKey($request->integer('build'))->first();
        }

        return $plan->builds()
            ->where('is_active', true)
            ->orderByDesc('is_open')
            ->orderBy('name')
            ->first();
    }
}
