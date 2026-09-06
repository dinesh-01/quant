<?php

namespace App\Http\Controllers\TestPlans;

use App\Actions\Authorization\RoleResolver;
use App\Concerns\PresentsAttachments;
use App\Enums\Ability;
use App\Enums\TesterAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Build;
use App\Models\Milestone;
use App\Models\Platform;
use App\Models\TestCase;
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
 * The plan as a workspace: its platforms, builds and linked cases.
 *
 * Reachable with any of the planning abilities on this plan, so a plan-role
 * linker or build manager can open it without also holding `create_test_plans`.
 * The plan list stays gated on that ability; this is the page the list
 * points at once a plan exists.
 */
class TestPlanContentsController extends Controller
{
    use PresentsAttachments;

    /**
     * @throws AuthorizationException
     */
    public function show(Request $request, TestPlan $testPlan, RoleResolver $roleResolver): Response
    {
        $user = $this->actingUser($request);

        $this->authorizeView($user, $testPlan);

        $testPlan->loadMissing('testProject');
        $project = $testPlan->testProject;
        $gate = Gate::forUser($user);

        $assignedPlatformIds = $testPlan->platforms()->pluck('platforms.id');

        $platforms = $project->platforms()
            ->alphabetically()
            ->get()
            ->map(fn (Platform $platform): array => [
                'id' => $platform->id,
                'name' => $platform->name,
                'enable_on_execution' => $platform->enable_on_execution,
                'is_open' => $platform->is_open,
                'assigned' => $assignedPlatformIds->contains($platform->id),
            ])
            ->all();

        $builds = $testPlan->builds()
            ->get()
            ->map(fn (Build $build): array => [
                'id' => $build->id,
                'name' => $build->name,
                'notes' => $build->notes,
                'is_active' => $build->is_active,
                'is_open' => $build->is_open,
                'release_date' => $build->release_date?->toDateString(),
            ])
            ->all();

        $itemModels = $testPlan->items()
            ->with(['testCaseVersion.testCase.testProject', 'testCaseVersion.testCase.latestVersion', 'platform'])
            ->get();

        $items = $itemModels
            ->map(fn (TestPlanItem $item): array => $this->itemProp($item))
            ->all();

        $linkable = TestCase::query()
            ->where('test_project_id', $project->id)
            ->with('latestVersion')
            ->orderBy('name')
            ->get()
            ->filter(fn (TestCase $case): bool => $case->latestVersion !== null)
            ->map(fn (TestCase $case): array => [
                'version_id' => $case->latestVersion->id,
                'name' => $case->name,
                'full_external_id' => "{$project->prefix}-{$case->external_id}",
                'version' => $case->latestVersion->version,
            ])
            ->values()
            ->all();

        return Inertia::render('test-plans/show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'prefix' => $project->prefix,
            ],
            'plan' => [
                'id' => $testPlan->id,
                'name' => $testPlan->name,
                'is_open' => $testPlan->is_open,
                'has_null_platform_items' => $itemModels->contains(
                    fn (TestPlanItem $item): bool => $item->platform_id === null,
                ),
            ],
            'platforms' => array_values($platforms),
            'builds' => array_values($builds),
            'items' => array_values($items),
            'linkable' => $linkable,
            'can' => [
                'editPlan' => $gate->allows(Ability::CreateTestPlans->value, $testPlan),
                'managePlanPlatforms' => $gate->allows(Ability::ManagePlanPlatforms->value, $testPlan),
                'manageBuilds' => $gate->allows(Ability::ManageBuilds->value, $testPlan),
                'planTestCases' => $gate->allows(Ability::PlanTestCases->value, $testPlan),
                'setUrgency' => $gate->allows(Ability::SetTestCaseUrgency->value, $testPlan),
                'updateLinkedVersions' => $gate->allows(Ability::UpdateLinkedTestCaseVersions->value, $testPlan),
                'viewPlatforms' => $gate->allows(Ability::ViewPlatforms->value, $project),
                'assignTesters' => $gate->allows(Ability::AssignTesters->value, $testPlan),
                'manageMilestones' => $gate->allows(Ability::ManageMilestones->value, $testPlan),
                'manageAttachments' => $gate->allows(Ability::CreateTestPlans->value, $testPlan),
                'viewAttachments' => $gate->allows(Ability::ViewExecutions->value, $testPlan)
                    || $gate->allows(Ability::CreateTestPlans->value, $testPlan),
            ],
            'attachments' => $this->attachmentProps($testPlan),
            'attachmentRules' => $this->attachmentRules(),
            'milestones' => $testPlan->milestones()
                ->get()
                ->map(fn (Milestone $milestone): array => [
                    'id' => $milestone->id,
                    'name' => $milestone->name,
                    'target_date' => $milestone->target_date->toDateString(),
                    'start_date' => $milestone->start_date?->toDateString(),
                    'high_percent' => $milestone->high_percent,
                    'medium_percent' => $milestone->medium_percent,
                    'low_percent' => $milestone->low_percent,
                ])
                ->all(),
            'selectedBuildId' => $this->selectedBuildId($request, $testPlan),
            'testers' => $this->assignableTesters($testPlan, $roleResolver),
            'assignments' => $this->assignmentProps($testPlan),
            'assignmentStatuses' => array_map(
                fn (TesterAssignmentStatus $status): array => [
                    'value' => $status->value,
                    'label' => str_replace('_', ' ', $status->value),
                ],
                TesterAssignmentStatus::cases(),
            ),
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeView(User $user, TestPlan $plan): void
    {
        $gate = Gate::forUser($user);

        $allowed = $gate->allows(Ability::CreateTestPlans->value, $plan)
            || $gate->allows(Ability::PlanTestCases->value, $plan)
            || $gate->allows(Ability::ManageBuilds->value, $plan)
            || $gate->allows(Ability::ManagePlanPlatforms->value, $plan)
            || $gate->allows(Ability::SetTestCaseUrgency->value, $plan)
            || $gate->allows(Ability::UpdateLinkedTestCaseVersions->value, $plan)
            || $gate->allows(Ability::AssignTesters->value, $plan)
            || $gate->allows(Ability::ManageMilestones->value, $plan)
            || $gate->allows(Ability::ExecuteTests->value, $plan)
            || $gate->allows(Ability::ViewExecutions->value, $plan);

        if (! $allowed) {
            throw new AuthorizationException;
        }
    }

    /**
     * @return array{id: int, sort_order: int, urgency: string, platform: array{id: int, name: string}|null, version_id: int, version: int, latest_version: int, test_case_id: int, test_case_name: string, full_external_id: string}
     */
    private function itemProp(TestPlanItem $item): array
    {
        $version = $item->testCaseVersion;
        $case = $version->testCase;

        return [
            'id' => $item->id,
            'sort_order' => $item->sort_order,
            'urgency' => $item->urgency->value,
            'platform' => $item->platform === null ? null : [
                'id' => $item->platform->id,
                'name' => $item->platform->name,
            ],
            'version_id' => $version->id,
            'version' => $version->version,
            'latest_version' => $case->latestVersion->version,
            'test_case_id' => $case->id,
            'test_case_name' => $case->name,
            'full_external_id' => $case->fullExternalId(),
        ];
    }

    private function selectedBuildId(Request $request, TestPlan $plan): ?int
    {
        $requested = $request->integer('build');

        if ($requested > 0 && $plan->builds()->whereKey($requested)->exists()) {
            return $requested;
        }

        return $plan->builds()->orderBy('name')->orderBy('id')->value('id');
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function assignableTesters(TestPlan $plan, RoleResolver $roleResolver): array
    {
        $plan->loadMissing('testProject');

        $memberIds = $plan->testProject->members()->pluck('users.id')
            ->merge($plan->members()->pluck('users.id'))
            ->unique();

        if ($memberIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('id', $memberIds)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $tester): bool => $roleResolver->allows($tester, Ability::ExecuteTests, $plan))
            ->map(fn (User $tester): array => [
                'id' => $tester->id,
                'name' => $tester->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, test_plan_item_id: int, build_id: int, user_id: int, user_name: string, status: string, deadline_at: string|null}>
     */
    private function assignmentProps(TestPlan $plan): array
    {
        return $plan->testerAssignments()
            ->with('user')
            ->orderBy('id')
            ->get()
            ->map(fn (TesterAssignment $assignment): array => [
                'id' => $assignment->id,
                'test_plan_item_id' => $assignment->test_plan_item_id,
                'build_id' => $assignment->build_id,
                'user_id' => $assignment->user_id,
                'user_name' => $assignment->user->name,
                'status' => $assignment->status->value,
                'deadline_at' => $assignment->deadline_at?->toDateString(),
            ])
            ->all();
    }
}
