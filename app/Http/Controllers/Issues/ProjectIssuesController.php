<?php

namespace App\Http\Controllers\Issues;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\ExecutionIssue;
use App\Models\TestProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Issues linked from executions in one project.
 */
class ProjectIssuesController extends Controller
{
    public function index(Request $request, TestProject $testProject): Response
    {
        $user = $this->actingUser($request);
        $gate = Gate::forUser($user);

        abort_unless(
            $gate->allows(Ability::ViewIssueTrackers->value, $testProject)
            || $gate->allows(Ability::ViewExecutions->value, $testProject),
            403,
        );

        $issues = ExecutionIssue::query()
            ->with([
                'execution.testPlan',
                'execution.testCaseVersion.testCase.testSuite',
            ])
            ->whereHas(
                'execution.testPlan',
                fn ($query) => $query->where('test_project_id', $testProject->id),
            )
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (ExecutionIssue $issue): array => [
                'id' => $issue->id,
                'issue_id' => $issue->issue_id,
                'issue_url' => $issue->issue_url,
                'issue_status' => $issue->issue_status,
                'issue_summary' => $issue->issue_summary,
                'plan' => $issue->execution->testPlan->name,
                'case' => $issue->execution->testCaseVersion->testCase->name,
                'external_id' => $issue->execution->testCaseVersion->testCase->fullExternalId(),
                'suite' => $issue->execution->testCaseVersion->testCase->testSuite?->name,
            ])
            ->all();

        return Inertia::render('issues/index', [
            'project' => [
                'id' => $testProject->id,
                'name' => $testProject->name,
            ],
            'issues' => $issues,
        ]);
    }
}
