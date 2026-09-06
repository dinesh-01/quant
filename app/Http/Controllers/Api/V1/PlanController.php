<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\AuthorizesApiPlanReads;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlanResource;
use App\Models\TestPlan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    use AuthorizesApiPlanReads;

    public function show(Request $request, TestPlan $testPlan): PlanResource
    {
        $this->authorizePlanRead($this->actingUser($request), $testPlan);

        return new PlanResource($testPlan);
    }
}
