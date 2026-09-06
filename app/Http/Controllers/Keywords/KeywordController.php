<?php

namespace App\Http\Controllers\Keywords;

use App\Actions\Keywords\CreateKeyword;
use App\Actions\Keywords\DeleteKeyword;
use App\Actions\Keywords\UpdateKeyword;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Keywords\KeywordStoreRequest;
use App\Http\Requests\Keywords\KeywordUpdateRequest;
use App\Models\Keyword;
use App\Models\TestProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A project's keyword vocabulary.
 *
 * Reading the list needs `view_keywords` and changing it needs
 * `manage_keywords`, which is the split legacy intended and then broke: its
 * edit screen accepted either right, so a view-only user could post a create,
 * an update or a delete to it (`keywordsEdit.php:339`), and the screen's own
 * `canManage` flag was hard-coded true before the real check overwrote it.
 */
class KeywordController extends Controller
{
    public function index(Request $request, TestProject $testProject): Response
    {
        $user = $this->actingUser($request);

        Gate::forUser($user)->authorize(Ability::ViewKeywords->value, $testProject);

        /*
         * The count is what makes the list usable: it says which keywords are
         * actually in play and warns how far a delete reaches. One aggregate
         * query, not one per row.
         */
        $keywords = $testProject->keywords()
            ->withCount('testCases')
            ->get()
            ->map(fn (Keyword $keyword): array => [
                'id' => $keyword->id,
                'name' => $keyword->name,
                'notes' => $keyword->notes,
                'test_cases_count' => (int) $keyword->test_cases_count,
            ])
            ->all();

        return Inertia::render('keywords/index', [
            'project' => $this->projectProp($testProject),
            'keywords' => array_values($keywords),
            'can' => [
                'manage' => Gate::forUser($user)->allows(Ability::ManageKeywords->value, $testProject),
            ],
        ]);
    }

    public function create(Request $request, TestProject $testProject): Response
    {
        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::ManageKeywords->value, $testProject);

        return Inertia::render('keywords/create', [
            'project' => $this->projectProp($testProject),
        ]);
    }

    public function store(
        KeywordStoreRequest $request,
        TestProject $testProject,
        CreateKeyword $createKeyword,
    ): RedirectResponse {
        $createKeyword($this->actingUser($request), $testProject, $request->keyword());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Keyword created.')]);

        return to_route('keywords.index', $testProject);
    }

    public function edit(Request $request, Keyword $keyword): Response
    {
        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::ManageKeywords->value, $keyword->testProject);

        return Inertia::render('keywords/edit', [
            'project' => $this->projectProp($keyword->testProject),
            'keyword' => [
                'id' => $keyword->id,
                'name' => $keyword->name,
                'notes' => $keyword->notes,
                'test_cases_count' => $keyword->testCases()->count(),
            ],
        ]);
    }

    public function update(
        KeywordUpdateRequest $request,
        Keyword $keyword,
        UpdateKeyword $updateKeyword,
    ): RedirectResponse {
        $updateKeyword($this->actingUser($request), $keyword, $request->keyword());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Keyword updated.')]);

        return to_route('keywords.index', $keyword->testProject);
    }

    public function destroy(
        Request $request,
        Keyword $keyword,
        DeleteKeyword $deleteKeyword,
    ): RedirectResponse {
        $project = $keyword->testProject;
        $name = $keyword->name;

        $deleteKeyword($this->actingUser($request), $keyword);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('":name" deleted.', ['name' => $name]),
        ]);

        return to_route('keywords.index', $project);
    }

    /**
     * @return array{id: int, name: string}
     */
    private function projectProp(TestProject $project): array
    {
        return ['id' => $project->id, 'name' => $project->name];
    }
}
