<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Executions\QueueReportedExecutions;
use App\Actions\Executions\ResolveReportedExecution;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBulkExecutionsRequest;
use App\Models\TestPlan;
use Illuminate\Http\JsonResponse;

class ExecutionBatchController extends Controller
{
    public function store(
        StoreBulkExecutionsRequest $request,
        TestPlan $testPlan,
        ResolveReportedExecution $resolveReportedExecution,
        QueueReportedExecutions $queueReportedExecutions,
    ): JsonResponse {
        $resolved = [];

        foreach ($request->results() as $index => $result) {
            $resolved[] = $resolveReportedExecution($testPlan, $result, 'results.'.$index.'.');
        }

        $accepted = $queueReportedExecutions(
            $this->actingUser($request),
            $testPlan,
            $resolved,
        );

        return response()->json([
            'data' => ['accepted' => $accepted],
        ], 202);
    }
}
