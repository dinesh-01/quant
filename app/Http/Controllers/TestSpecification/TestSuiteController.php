<?php

namespace App\Http\Controllers\TestSpecification;

use App\Actions\CustomFields\SaveCustomFieldValues;
use App\Actions\TestSpecification\CopyTestSuite;
use App\Actions\TestSpecification\CreateTestSuite;
use App\Actions\TestSpecification\DeleteTestSuite;
use App\Actions\TestSpecification\MoveTestSuite;
use App\Actions\TestSpecification\ReorderTestSuites;
use App\Actions\TestSpecification\UpdateTestSuite;
use App\Http\Controllers\Controller;
use App\Http\Requests\TestSpecification\TestSuiteCopyRequest;
use App\Http\Requests\TestSpecification\TestSuiteMoveRequest;
use App\Http\Requests\TestSpecification\TestSuiteReorderRequest;
use App\Http\Requests\TestSpecification\TestSuiteStoreRequest;
use App\Http\Requests\TestSpecification\TestSuiteUpdateRequest;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Write endpoints for suites.
 *
 * Each method validates, resolves any referenced models, and hands off to the
 * action, which is where authorization and the domain rules live.
 */
class TestSuiteController extends Controller
{
    public function store(
        TestSuiteStoreRequest $request,
        TestProject $testProject,
        CreateTestSuite $createTestSuite,
    ): RedirectResponse {
        $suite = $createTestSuite(
            $this->actingUser($request),
            $testProject,
            $this->suiteOrNull($request->parentSuiteId()),
            $request->suiteAttributes(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test suite created.')]);

        return to_route('specification.suites.show', [$testProject, $suite]);
    }

    public function update(
        TestSuiteUpdateRequest $request,
        TestSuite $testSuite,
        UpdateTestSuite $updateTestSuite,
        SaveCustomFieldValues $saveCustomFieldValues,
    ): RedirectResponse {
        $updateTestSuite(
            $this->actingUser($request),
            $testSuite,
            $request->suiteAttributes(),
        );

        $saveCustomFieldValues(
            $this->actingUser($request),
            $testSuite,
            $request->customFieldAnswers(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test suite updated.')]);

        return back();
    }

    public function destroy(
        Request $request,
        TestSuite $testSuite,
        DeleteTestSuite $deleteTestSuite,
    ): RedirectResponse {
        $project = $testSuite->testProject;

        $deleteTestSuite($this->actingUser($request), $testSuite);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test suite deleted.')]);

        return to_route('specification.show', $project);
    }

    public function move(
        TestSuiteMoveRequest $request,
        TestSuite $testSuite,
        MoveTestSuite $moveTestSuite,
    ): RedirectResponse {
        $moveTestSuite(
            $this->actingUser($request),
            $testSuite,
            $this->suiteOrNull($request->validated('parent_id')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test suite moved.')]);

        return back();
    }

    public function copy(
        TestSuiteCopyRequest $request,
        TestSuite $testSuite,
        CopyTestSuite $copyTestSuite,
    ): RedirectResponse {
        $targetProjectId = $request->validated('test_project_id');

        $copy = $copyTestSuite(
            $this->actingUser($request),
            $testSuite,
            $this->suiteOrNull($request->validated('parent_id')),
            is_numeric($targetProjectId)
                ? TestProject::query()->findOrFail((int) $targetProjectId)
                : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test suite copied.')]);

        return to_route('specification.suites.show', [$copy->testProject, $copy]);
    }

    public function reorder(
        TestSuiteReorderRequest $request,
        TestProject $testProject,
        ReorderTestSuites $reorderTestSuites,
    ): RedirectResponse {
        $reorderTestSuites(
            $this->actingUser($request),
            $testProject,
            $this->suiteOrNull($request->validated('parent_id')),
            array_map('intval', (array) $request->validated('order')),
        );

        return back();
    }

    private function suiteOrNull(mixed $id): ?TestSuite
    {
        return is_numeric($id) ? TestSuite::query()->findOrFail((int) $id) : null;
    }
}
