<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\AuthorizesApiPlanReads;
use App\Concerns\PaginatesApiCursors;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlanItemResource;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanCaseController extends Controller
{
    use AuthorizesApiPlanReads;
    use PaginatesApiCursors;

    public function index(Request $request, TestPlan $testPlan): AnonymousResourceCollection
    {
        $this->authorizePlanRead($this->actingUser($request), $testPlan);

        $items = TestPlanItem::query()
            ->where('test_plan_id', $testPlan->getKey())
            ->with([
                'platform',
                'testCaseVersion.testCase.testProject',
                'testCaseVersion.scriptLinks',
            ])
            ->orderBy('id')
            ->cursorPaginate($this->cursorPerPage($request));

        return PlanItemResource::collection($items)->additional([
            'meta' => $this->cursorMeta($items),
        ]);
    }
}
