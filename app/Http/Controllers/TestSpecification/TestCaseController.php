<?php

namespace App\Http\Controllers\TestSpecification;

use App\Actions\TestSpecification\CopyTestCase;
use App\Actions\TestSpecification\CreateTestCase;
use App\Actions\TestSpecification\DeleteTestCase;
use App\Actions\TestSpecification\MoveTestCase;
use App\Actions\TestSpecification\ReorderTestCases;
use App\Actions\TestSpecification\UpdateTestCase;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\TestSpecification\TestCaseCopyRequest;
use App\Http\Requests\TestSpecification\TestCaseMoveRequest;
use App\Http\Requests\TestSpecification\TestCaseReorderRequest;
use App\Http\Requests\TestSpecification\TestCaseStoreRequest;
use App\Http\Requests\TestSpecification\TestCaseUpdateRequest;
use App\Models\TestCase;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Write endpoints for test cases.
 */
class TestCaseController extends Controller
{
    public function create(Request $request, TestProject $testProject): Response
    {
        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::ManageTestCases->value, $testProject);

        $suites = $testProject->testSuites()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (TestSuite $suite): array => [
                'id' => $suite->id,
                'name' => $suite->name,
            ])
            ->all();

        $suiteId = TestSuite::query()
            ->where('test_project_id', $testProject->id)
            ->whereKey($request->integer('suite'))
            ->value('id');

        return Inertia::render('test-specification/create', [
            'project' => [
                'id' => $testProject->id,
                'name' => $testProject->name,
                'prefix' => $testProject->prefix,
            ],
            'suites' => $suites,
            'suite_id' => $suiteId !== null ? (int) $suiteId : ($suites[0]['id'] ?? null),
        ]);
    }

    public function store(
        TestCaseStoreRequest $request,
        TestSuite $testSuite,
        CreateTestCase $createTestCase,
    ): RedirectResponse {
        $case = $createTestCase(
            $this->actingUser($request),
            $testSuite,
            $request->caseAttributes(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test case created.')]);

        return to_route('specification.cases.show', [$testSuite->testProject, $case]);
    }

    public function update(
        TestCaseUpdateRequest $request,
        TestCase $testCase,
        UpdateTestCase $updateTestCase,
    ): RedirectResponse {
        $updateTestCase($this->actingUser($request), $testCase, $request->caseAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test case renamed.')]);

        return back();
    }

    public function destroy(
        Request $request,
        TestCase $testCase,
        DeleteTestCase $deleteTestCase,
    ): RedirectResponse {
        $project = $testCase->testProject;
        $suite = $testCase->testSuite;

        $deleteTestCase($this->actingUser($request), $testCase);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test case deleted.')]);

        return to_route('specification.suites.show', [$project, $suite]);
    }

    public function move(
        TestCaseMoveRequest $request,
        TestCase $testCase,
        MoveTestCase $moveTestCase,
    ): RedirectResponse {
        $moveTestCase(
            $this->actingUser($request),
            $testCase,
            TestSuite::query()->findOrFail((int) $request->validated('test_suite_id')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test case moved.')]);

        return back();
    }

    public function copy(
        TestCaseCopyRequest $request,
        TestCase $testCase,
        CopyTestCase $copyTestCase,
    ): RedirectResponse {
        $copy = $copyTestCase(
            $this->actingUser($request),
            $testCase,
            TestSuite::query()->findOrFail((int) $request->validated('test_suite_id')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test case copied.')]);

        return to_route('specification.cases.show', [$copy->testProject, $copy]);
    }

    public function reorder(
        TestCaseReorderRequest $request,
        TestSuite $testSuite,
        ReorderTestCases $reorderTestCases,
    ): RedirectResponse {
        $reorderTestCases(
            $this->actingUser($request),
            $testSuite,
            array_map('intval', (array) $request->validated('order')),
        );

        return back();
    }
}
