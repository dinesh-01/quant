<?php

namespace App\Http\Controllers\CodeTrackers;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\TestCaseScriptLink;
use App\Models\TestProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Automation scripts linked to cases — the coverage dashboard has no % table.
 */
class CodeCoverageController extends Controller
{
    public function index(Request $request, TestProject $testProject): Response
    {
        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::ViewCodeTrackers->value, $testProject);

        $cases = $testProject->testCases()->count();
        $links = TestCaseScriptLink::query()
            ->with(['testCaseVersion.testCase.testSuite'])
            ->whereHas(
                'testCaseVersion.testCase',
                fn ($query) => $query->where('test_project_id', $testProject->id),
            )
            ->orderBy('repository')
            ->orderBy('path')
            ->get()
            ->map(fn (TestCaseScriptLink $link): array => [
                'id' => $link->id,
                'repository' => $link->repository,
                'path' => $link->path,
                'branch' => $link->branch,
                'case' => $link->testCaseVersion->testCase->name,
                'external_id' => $link->testCaseVersion->testCase->fullExternalId(),
                'suite' => $link->testCaseVersion->testCase->testSuite?->name,
            ])
            ->all();

        return Inertia::render('code-coverage/index', [
            'project' => [
                'id' => $testProject->id,
                'name' => $testProject->name,
            ],
            'cases' => $cases,
            'links' => $links,
            'tracker' => $testProject->codeTracker === null ? null : [
                'name' => $testProject->codeTracker->name,
                'type' => $testProject->codeTracker->type->value,
                'is_enabled' => $testProject->codeTracker->is_enabled,
            ],
        ]);
    }
}
