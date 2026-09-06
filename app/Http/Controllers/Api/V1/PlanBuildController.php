<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\AuthorizesApiPlanReads;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BuildResource;
use App\Models\Build;
use App\Models\TestPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanBuildController extends Controller
{
    use AuthorizesApiPlanReads;

    public function index(Request $request, TestPlan $testPlan): AnonymousResourceCollection
    {
        $this->authorizePlanRead($this->actingUser($request), $testPlan);

        $builds = Build::query()
            ->where('test_plan_id', $testPlan->getKey())
            ->orderBy('id')
            ->get();

        return BuildResource::collection($builds);
    }
}
