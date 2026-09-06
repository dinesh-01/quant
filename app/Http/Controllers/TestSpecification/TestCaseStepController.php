<?php

namespace App\Http\Controllers\TestSpecification;

use App\Actions\TestSpecification\CreateTestCaseStep;
use App\Actions\TestSpecification\DeleteTestCaseStep;
use App\Actions\TestSpecification\ReorderTestCaseSteps;
use App\Actions\TestSpecification\UpdateTestCaseStep;
use App\Http\Controllers\Controller;
use App\Http\Requests\TestSpecification\TestCaseStepReorderRequest;
use App\Http\Requests\TestSpecification\TestCaseStepRequest;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Step endpoints, all of which refuse a frozen version inside the action.
 */
class TestCaseStepController extends Controller
{
    public function store(
        TestCaseStepRequest $request,
        TestCaseVersion $testCaseVersion,
        CreateTestCaseStep $createTestCaseStep,
    ): RedirectResponse {
        $createTestCaseStep(
            $this->actingUser($request),
            $testCaseVersion,
            $request->stepAttributes(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Step added.')]);

        return back();
    }

    public function update(
        TestCaseStepRequest $request,
        TestCaseStep $testCaseStep,
        UpdateTestCaseStep $updateTestCaseStep,
    ): RedirectResponse {
        $updateTestCaseStep(
            $this->actingUser($request),
            $testCaseStep,
            $request->stepAttributes(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Step saved.')]);

        return back();
    }

    public function destroy(
        Request $request,
        TestCaseStep $testCaseStep,
        DeleteTestCaseStep $deleteTestCaseStep,
    ): RedirectResponse {
        $deleteTestCaseStep($this->actingUser($request), $testCaseStep);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Step removed.')]);

        return back();
    }

    public function reorder(
        TestCaseStepReorderRequest $request,
        TestCaseVersion $testCaseVersion,
        ReorderTestCaseSteps $reorderTestCaseSteps,
    ): RedirectResponse {
        $reorderTestCaseSteps(
            $this->actingUser($request),
            $testCaseVersion,
            array_map('intval', (array) $request->validated('order')),
        );

        return back();
    }
}
