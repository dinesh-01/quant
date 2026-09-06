<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TestSpecification\CreateTestCase;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\TestSpecification\TestCaseStoreRequest;
use App\Http\Resources\Api\V1\CaseResource;
use App\Models\TestCase;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CaseController extends Controller
{
    public function show(Request $request, TestCase $testCase): CaseResource
    {
        $testCase->loadMissing('testProject');

        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::ViewTestCases->value, $testCase->testProject);

        $testCase->load(['latestVersion.scriptLinks']);

        return new CaseResource($testCase);
    }

    public function store(
        TestCaseStoreRequest $request,
        TestProject $testProject,
        TestSuite $testSuite,
        CreateTestCase $createTestCase,
    ): JsonResponse {
        abort_unless($testSuite->test_project_id === $testProject->getKey(), 404);

        $case = $createTestCase(
            $this->actingUser($request),
            $testSuite,
            $request->caseAttributes(),
        );

        $case->load(['testProject', 'latestVersion.scriptLinks']);

        return (new CaseResource($case))
            ->response()
            ->setStatusCode(201);
    }
}
