<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Authorization\RoleResolver;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlanResource;
use App\Models\TestPlan;
use App\Models\TestProject;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectPlanController extends Controller
{
    public function index(
        Request $request,
        TestProject $testProject,
        RoleResolver $roleResolver,
    ): AnonymousResourceCollection {
        $user = $this->actingUser($request);

        /** @var EloquentCollection<int, TestPlan> $plans */
        $plans = $testProject->testPlans()->orderBy('name')->get();

        $readable = $roleResolver
            ->plansAllowing($user, Ability::ViewExecutions, $plans)
            ->merge($roleResolver->plansAllowing($user, Ability::ExecuteTests, $plans))
            ->unique('id')
            ->values();

        return PlanResource::collection($readable);
    }
}
