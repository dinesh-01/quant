<?php

namespace App\Http\Controllers\TestSpecification;

use App\Actions\TestSpecification\CreateTestCaseRelation;
use App\Actions\TestSpecification\DeleteTestCaseRelation;
use App\Http\Controllers\Controller;
use App\Http\Requests\TestSpecification\CreateTestCaseRelationRequest;
use App\Models\TestCase;
use App\Models\TestCaseRelation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TestCaseRelationController extends Controller
{
    public function store(
        CreateTestCaseRelationRequest $request,
        TestCase $testCase,
        CreateTestCaseRelation $createTestCaseRelation,
    ): RedirectResponse {
        $createTestCaseRelation(
            $this->actingUser($request),
            $testCase,
            $request->destination(),
            $request->relationType(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Relation added.')]);

        return to_route('specification.cases.show', [
            $testCase->test_project_id,
            $testCase,
        ]);
    }

    public function destroy(
        Request $request,
        TestCaseRelation $testCaseRelation,
        DeleteTestCaseRelation $deleteTestCaseRelation,
    ): RedirectResponse {
        $testCaseRelation->loadMissing('source');

        $case = $testCaseRelation->source;

        $deleteTestCaseRelation($this->actingUser($request), $testCaseRelation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Relation removed.')]);

        return to_route('specification.cases.show', [
            $case->test_project_id,
            $case,
        ]);
    }
}
