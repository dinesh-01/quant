<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesApiCursors;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CaseResource;
use App\Models\TestCase;
use App\Models\TestProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProjectCaseController extends Controller
{
    use PaginatesApiCursors;

    public function index(Request $request, TestProject $testProject): AnonymousResourceCollection
    {
        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::ViewTestCases->value, $testProject);

        $cases = TestCase::query()
            ->where('test_project_id', $testProject->getKey())
            ->with(['testProject', 'latestVersion.scriptLinks'])
            ->orderBy('id')
            ->cursorPaginate($this->cursorPerPage($request));

        return CaseResource::collection($cases)->additional([
            'meta' => $this->cursorMeta($cases),
        ]);
    }
}
