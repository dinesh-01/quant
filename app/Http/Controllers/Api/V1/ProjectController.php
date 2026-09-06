<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Authorization\RoleResolver;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\TestProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index(Request $request, RoleResolver $roleResolver): AnonymousResourceCollection
    {
        $projects = TestProject::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return ProjectResource::collection(
            $roleResolver->projectsAllowing(
                $this->actingUser($request),
                Ability::ViewTestCases,
                $projects,
            )->values(),
        );
    }

    public function show(Request $request, TestProject $testProject): ProjectResource
    {
        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::ViewTestCases->value, $testProject);

        return new ProjectResource($testProject);
    }
}
