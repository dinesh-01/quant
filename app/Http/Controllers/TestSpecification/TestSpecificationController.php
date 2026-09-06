<?php

namespace App\Http\Controllers\TestSpecification;

use App\Actions\Authorization\RoleResolver;
use App\Actions\TestSpecification\ExpandGhostMarkup;
use App\Concerns\PresentsAttachments;
use App\Concerns\PresentsCustomFields;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\TestSpecification\SpecificationSearchRequest;
use App\Models\Keyword;
use App\Models\Platform;
use App\Models\RequirementCoverage;
use App\Models\RequirementVersion;
use App\Models\TestCase;
use App\Models\TestCaseRelation;
use App\Models\TestCaseScriptLink;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the specification screen: a suite tree beside a detail pane.
 *
 * All three show methods render the same page component, so selecting a node is
 * an ordinary Inertia visit that keeps the tree on screen and gives every node
 * a deep link. Legacy used a frameset and had neither.
 *
 * Abilities are scoped to the test project, so one `view_test_cases` check
 * covers every node in the tree; there is no per-node grant to check.
 */
class TestSpecificationController extends Controller
{
    use PresentsAttachments;
    use PresentsCustomFields;

    /**
     * Show the project's tree with nothing selected.
     */
    public function show(Request $request, TestProject $testProject): Response
    {
        Gate::authorize(Ability::ViewTestCases->value, $testProject);

        return $this->page($request, $testProject);
    }

    /**
     * Show the tree with a suite selected.
     */
    public function showSuite(Request $request, TestProject $testProject, TestSuite $testSuite): Response
    {
        Gate::authorize(Ability::ViewTestCases->value, $testProject);

        return $this->page($request, $testProject, [
            'selected' => [
                'type' => 'suite',
                'suite' => [
                    'id' => $testSuite->id,
                    'name' => $testSuite->name,
                    'description' => $testSuite->description,
                    'parent_id' => $testSuite->parent_id,
                    'path' => $testSuite->path()->map(fn (TestSuite $ancestor): array => [
                        'id' => $ancestor->id,
                        'name' => $ancestor->name,
                    ])->all(),
                    'attachments' => $this->attachmentProps($testSuite),
                    'custom_fields' => $this->customFieldProps($testSuite),
                ],
            ],
        ]);
    }

    /**
     * Show the tree with a test case selected.
     *
     * `?version=N` picks a version by its number rather than its id, so the
     * link a reviewer pastes reads as the version they are talking about and
     * survives being retyped. Without it the newest version is shown, which is
     * what the authoring screens want.
     *
     * Anything that was asked for and cannot be resolved is a 404, whether it
     * is a number that no longer exists or not a number at all. Falling back to
     * the newest would serve different content under the same URL, so a stale
     * link to a deleted version would look like it still worked.
     */
    public function showCase(
        Request $request,
        TestProject $testProject,
        TestCase $testCase,
        ExpandGhostMarkup $expandGhostMarkup,
    ): Response {
        Gate::authorize(Ability::ViewTestCases->value, $testProject);

        $testProject->loadMissing('codeTracker');

        $testCase->load([
            'versions.steps',
            'versions.author',
            'versions.updater',
            'versions.platforms',
            'versions.requirementCoverages.requirementVersion.requirement',
            'versions.scriptLinks',
            'testSuite',
            'keywords',
            'outgoingRelations.destination.testProject',
            'incomingRelations.source.testProject',
        ]);

        $asked = $request->has('version');

        $shown = $asked
            ? $testCase->versions->firstWhere('version', $request->integer('version'))
            : $testCase->versions->last();

        abort_if($asked && $shown === null, 404);

        return $this->page($request, $testProject, [
            'selected' => [
                'type' => 'case',
                'case' => [
                    'id' => $testCase->id,
                    'name' => $testCase->name,
                    /*
                     * Keywords belong to the case rather than to a version, so
                     * they sit here and do not change when the reader switches
                     * versions.
                     */
                    'keywords' => $testCase->keywords
                        ->map(fn (Keyword $keyword): array => [
                            'id' => $keyword->id,
                            'name' => $keyword->name,
                        ])
                        ->all(),
                    'external_id' => $testCase->external_id,
                    'full_external_id' => "{$testProject->prefix}-{$testCase->external_id}",
                    'test_suite_id' => $testCase->test_suite_id,
                    'suite_name' => $testCase->testSuite->name,
                    'versions' => $testCase->versions
                        ->map(fn (TestCaseVersion $version): array => [
                            'id' => $version->id,
                            'version' => $version->version,
                            'is_open' => $version->is_open,
                        ])
                        ->all(),
                    'version' => $shown === null ? null : $this->version($shown, $testProject, $expandGhostMarkup),
                    'relations' => $this->relationProps($testCase),
                    'relatable' => $this->relatableProps($testProject, $testCase),
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function version(
        TestCaseVersion $version,
        TestProject $project,
        ExpandGhostMarkup $expandGhostMarkup,
    ): array {
        $forDisplay = ! $version->is_open
            || ! Gate::allows(Ability::ManageTestCases->value, $project);

        $text = function (?string $html, string $field) use ($forDisplay, $expandGhostMarkup, $project): ?string {
            if (! $forDisplay || $html === null || $html === '') {
                return $html;
            }

            return $expandGhostMarkup($html, $project, $field);
        };

        return [
            'id' => $version->id,
            'version' => $version->version,
            'status' => $version->status->value,
            'summary' => $text($version->summary, 'summary'),
            'preconditions' => $text($version->preconditions, 'preconditions'),
            'importance' => $version->importance->value,
            'execution_type' => $version->execution_type->value,
            'estimated_duration' => $version->estimated_duration,
            'is_open' => $version->is_open,
            'author' => $version->author?->name,
            'updater' => $version->updater?->name,
            /*
             * Attachments hang off the *version*, so switching version switches
             * the evidence with it — which is the point of storing them there.
             */
            'attachments' => $this->attachmentProps($version),
            /* Per version too, and for the same reason. */
            'custom_fields' => $this->customFieldProps($version),
            /*
             * Platforms hang off the version, the opposite of keywords: a
             * claim about this revision of the steps.
             */
            'platforms' => $version->platforms
                ->sortBy('name')
                ->values()
                ->map(fn (Platform $platform): array => [
                    'id' => $platform->id,
                    'name' => $platform->name,
                ])
                ->all(),
            'steps' => $version->steps->map(fn (TestCaseStep $step): array => [
                'id' => $step->id,
                'sort_order' => $step->sort_order,
                'actions' => $text($step->actions, 'actions'),
                'expected_results' => $text($step->expected_results, 'expected_results'),
                'execution_type' => $step->execution_type->value,
            ])->all(),
            'coverages' => $version->requirementCoverages->map(fn (RequirementCoverage $coverage): array => [
                'id' => $coverage->id,
                'requirement_id' => $coverage->requirementVersion->requirement_id,
                'requirement_version' => $coverage->requirementVersion->version,
                'doc_id' => $coverage->requirementVersion->requirement->doc_id,
                'name' => $coverage->requirementVersion->requirement->name,
            ])->all(),
            'coverable' => $this->coverableRequirements($project, $version),
            'script_links' => $version->scriptLinks->map(fn (TestCaseScriptLink $link): array => [
                'id' => $link->id,
                'project_key' => $link->project_key,
                'repository' => $link->repository,
                'path' => $link->path,
                'branch' => $link->branch,
                'commit' => $link->commit,
                'url' => $project->codeTracker?->is_enabled === true
                    ? $project->codeTracker->urlFor($link)
                    : null,
            ])->all(),
        ];
    }

    /**
     * @return list<array{id: int, requirement_id: int, version: int, doc_id: string, name: string}>
     */
    private function coverableRequirements(TestProject $project, TestCaseVersion $version): array
    {
        $linked = $version->requirementCoverages->pluck('requirement_version_id')->all();

        return RequirementVersion::query()
            ->whereHas('requirement', fn ($query) => $query->where('test_project_id', $project->id))
            ->with('requirement')
            ->whereKeyNot($linked)
            ->orderBy('requirement_id')
            ->orderBy('version')
            ->get()
            ->map(fn (RequirementVersion $requirementVersion): array => [
                'id' => $requirementVersion->id,
                'requirement_id' => $requirementVersion->requirement_id,
                'version' => $requirementVersion->version,
                'doc_id' => $requirementVersion->requirement->doc_id,
                'name' => $requirementVersion->requirement->name,
            ])
            ->all();
    }

    /**
     * Search the project's test cases by name or external id.
     *
     * Returns JSON rather than a page so the sidebar can search as the user
     * types through `useHttp` without pushing history entries.
     */
    public function search(SpecificationSearchRequest $request, TestProject $testProject): JsonResponse
    {
        Gate::authorize(Ability::ViewTestCases->value, $testProject);

        $matches = TestCase::query()
            ->where('test_project_id', $testProject->id)
            ->matching((string) $request->validated('term'))
            ->with('testSuite:id,name')
            ->orderBy('external_id')
            ->limit(50)
            ->get();

        return response()->json([
            'results' => $matches->map(fn (TestCase $case): array => [
                'id' => $case->id,
                'name' => $case->name,
                'full_external_id' => "{$testProject->prefix}-{$case->external_id}",
                'suite_name' => $case->testSuite->name,
            ])->all(),
        ]);
    }

    /**
     * Other projects this person may copy into, with each project's suite tree.
     */
    public function copyTargets(
        TestProject $testProject,
        RoleResolver $roleResolver,
    ): JsonResponse {
        Gate::authorize(Ability::ManageTestCases->value, $testProject);

        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $projects = $roleResolver->projectsAllowing(
            $user,
            Ability::ManageTestCases,
            TestProject::query()->whereKeyNot($testProject->id)->orderBy('name')->get(),
        );

        return response()->json([
            'projects' => $projects->map(fn (TestProject $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'tree' => $this->suiteTree($project),
            ])->values()->all(),
        ]);
    }

    /**
     * Build the page props shared by every view of this screen.
     *
     * The tree costs two queries regardless of project size: one for every
     * suite, one for every case.
     *
     * @param  array<string, mixed>  $extra
     */
    private function page(Request $request, TestProject $testProject, array $extra = []): Response
    {
        $filter = $this->keywordFilter($request, $testProject);

        return Inertia::render('test-specification/index', [
            'project' => [
                'id' => $testProject->id,
                'name' => $testProject->name,
                'prefix' => $testProject->prefix,
            ],
            'tree' => $this->tree($testProject, $filter),
            'can' => [
                'manage' => Gate::allows(Ability::ManageTestCases->value, $testProject),
                'freeze' => Gate::allows(Ability::FreezeTestCases->value, $testProject),
                'deleteFrozen' => Gate::allows(Ability::DeleteFrozenTestCaseVersions->value, $testProject),
                'assignKeywords' => Gate::allows(Ability::AssignKeywords->value, $testProject),
                'viewKeywords' => Gate::allows(Ability::ViewKeywords->value, $testProject),
                'viewPlatforms' => Gate::allows(Ability::ViewPlatforms->value, $testProject),
                'coverage' => Gate::allows(Ability::ManageRequirementCoverage->value, $testProject),
            ],
            /*
             * The vocabulary is for the filter and the assignment picker, so it
             * is only sent to someone who can use one of them. A case's own
             * keywords are part of the case and always sent.
             */
            'keywords' => $this->vocabulary($testProject),
            'platforms' => $this->platformVocabulary($testProject),
            'keywordFilter' => $filter,
            'attachmentRules' => $this->attachmentRules(),
            'selected' => null,
            ...$extra,
        ]);
    }

    /**
     * The keywords the tree is being filtered by, and how.
     *
     * Requested ids are intersected with the project's own keywords, so an id
     * from another project cannot silently narrow the tree to nothing and the
     * screen can echo back exactly what is being applied.
     *
     * @return array{ids: list<int>, match: string}
     */
    private function keywordFilter(Request $request, TestProject $testProject): array
    {
        /** @var array<int, mixed> $requested */
        $requested = (array) $request->query('keywords', []);

        $ids = array_values(array_filter(
            array_map(intval(...), $requested),
            fn (int $id): bool => $id > 0,
        ));

        /** @var list<int> $valid */
        $valid = $ids === [] ? [] : Keyword::query()
            ->forProject($testProject)
            ->whereKey($ids)
            ->pluck('id')
            ->all();

        return [
            'ids' => $valid,
            'match' => $request->query('keyword_match') === 'all' ? 'all' : 'any',
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function vocabulary(TestProject $testProject): array
    {
        $maySee = Gate::allows(Ability::ViewKeywords->value, $testProject)
            || Gate::allows(Ability::AssignKeywords->value, $testProject);

        if (! $maySee) {
            return [];
        }

        return $testProject->keywords()
            ->get(['id', 'name'])
            ->map(fn (Keyword $keyword): array => ['id' => $keyword->id, 'name' => $keyword->name])
            ->all();
    }

    /**
     * Design-enabled platforms the version picker can offer.
     *
     * Sent only to someone who may edit cases, because tagging a revision is
     * `manage_test_cases`. A version's own platforms travel with the version.
     *
     * @return array<int, array{id: int, name: string, is_open: bool}>
     */
    private function platformVocabulary(TestProject $testProject): array
    {
        if (! Gate::allows(Ability::ManageTestCases->value, $testProject)) {
            return [];
        }

        return $testProject->platforms()
            ->where('enable_on_design', true)
            ->get(['id', 'name', 'is_open'])
            ->map(fn (Platform $platform): array => [
                'id' => $platform->id,
                'name' => $platform->name,
                'is_open' => $platform->is_open,
            ])
            ->all();
    }

    /**
     * @param  array{ids: list<int>, match: string}  $filter
     * @return array<int, array<string, mixed>>
     */
    private function tree(TestProject $testProject, array $filter): array
    {
        $filtering = $filter['ids'] !== [];

        $casesBySuite = TestCase::query()
            ->where('test_project_id', $testProject->id)
            ->when($filtering, fn ($query) => $filter['match'] === 'all'
                ? $query->withAllKeywords($filter['ids'])
                : $query->withAnyKeyword($filter['ids']))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'test_suite_id', 'external_id', 'name'])
            ->groupBy('test_suite_id');

        /**
         * While filtering, a suite is kept only if it holds a matching case or
         * an ancestor of one. Showing the whole tree with most of it empty
         * would leave the reader hunting for the matches, which is the thing
         * they asked to be shown.
         */
        $node = function (TestSuite $suite) use (&$node, $casesBySuite, $testProject, $filtering): ?array {
            $children = $suite->children
                ->map($node)
                ->filter()
                ->values()
                ->all();

            $cases = $casesBySuite->get($suite->id, collect())
                ->map(fn (TestCase $case): array => [
                    'id' => $case->id,
                    'name' => $case->name,
                    'full_external_id' => "{$testProject->prefix}-{$case->external_id}",
                ])->all();

            if ($filtering && $cases === [] && $children === []) {
                return null;
            }

            return [
                'id' => $suite->id,
                'name' => $suite->name,
                'children' => $children,
                'cases' => $cases,
            ];
        };

        return TestSuite::treeFor($testProject)
            ->map($node)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, type: string, label: string, other_id: int, other_name: string, full_external_id: string, outgoing: bool}>
     */
    private function relationProps(TestCase $case): array
    {
        $outgoing = $case->outgoingRelations->map(fn (TestCaseRelation $relation): array => [
            'id' => $relation->id,
            'type' => $relation->type->value,
            'label' => $relation->type->outgoingLabel(),
            'other_id' => $relation->destination_id,
            'other_name' => $relation->destination->name,
            'full_external_id' => $relation->destination->fullExternalId(),
            'outgoing' => true,
        ]);

        $incoming = $case->incomingRelations->map(fn (TestCaseRelation $relation): array => [
            'id' => $relation->id,
            'type' => $relation->type->value,
            'label' => $relation->type->incomingLabel(),
            'other_id' => $relation->source_id,
            'other_name' => $relation->source->name,
            'full_external_id' => $relation->source->fullExternalId(),
            'outgoing' => false,
        ]);

        return $outgoing->concat($incoming)->values()->all();
    }

    /**
     * @return list<array{id: int, name: string, children: list<array<string, mixed>>}>
     */
    private function suiteTree(TestProject $project): array
    {
        $map = function (TestSuite $suite) use (&$map): array {
            return [
                'id' => $suite->id,
                'name' => $suite->name,
                'children' => $suite->children->map($map)->values()->all(),
            ];
        };

        return TestSuite::treeFor($project)->map($map)->values()->all();
    }

    /**
     * @return list<array{id: int, name: string, full_external_id: string}>
     */
    private function relatableProps(TestProject $project, TestCase $case): array
    {
        return TestCase::query()
            ->where('test_project_id', $project->id)
            ->whereKeyNot($case->getKey())
            ->with('testProject')
            ->orderBy('name')
            ->get()
            ->map(fn (TestCase $other): array => [
                'id' => $other->id,
                'name' => $other->name,
                'full_external_id' => $other->fullExternalId(),
            ])
            ->all();
    }
}
