<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Executions\RecordExecution;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreExecutionRequest;
use App\Http\Resources\Api\V1\ExecutionResource;
use App\Models\TestPlan;
use Illuminate\Http\JsonResponse;

class ExecutionController extends Controller
{
    public function store(
        StoreExecutionRequest $request,
        TestPlan $testPlan,
        RecordExecution $recordExecution,
    ): JsonResponse {
        $item = $request->planItem();

        abort_unless($item->test_plan_id === $testPlan->getKey(), 404);

        $execution = $recordExecution(
            $this->actingUser($request),
            $item,
            $request->build(),
            $request->executionAttributes(),
            $request->shouldComplete(),
        );

        return (new ExecutionResource($execution))
            ->response()
            ->setStatusCode(201);
    }
}
