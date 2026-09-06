<?php

namespace App\Http\Controllers\Requirements;

use App\Enums\Ability;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use App\Http\Controllers\Controller;
use App\Models\Requirement;
use App\Models\RequirementCoverage;
use App\Models\RequirementSpec;
use App\Models\RequirementVersion;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the requirements screen: a spec tree beside a detail pane.
 */
class RequirementsController extends Controller
{
    public function show(Request $request, TestProject $testProject): Response
    {
        Gate::authorize(Ability::ViewRequirements->value, $testProject);

        return $this->page($testProject);
    }

    public function showSpec(Request $request, TestProject $testProject, RequirementSpec $requirementSpec): Response
    {
        Gate::authorize(Ability::ViewRequirements->value, $testProject);

        return $this->page($testProject, [
            'selected' => [
                'type' => 'spec',
                'spec' => [
                    'id' => $requirementSpec->id,
                    'name' => $requirementSpec->name,
                    'doc_id' => $requirementSpec->doc_id,
                    'description' => $requirementSpec->description,
                    'parent_id' => $requirementSpec->parent_id,
                    'path' => $requirementSpec->path()->map(fn (RequirementSpec $ancestor): array => [
                        'id' => $ancestor->id,
                        'name' => $ancestor->name,
                        'doc_id' => $ancestor->doc_id,
                    ])->all(),
                ],
            ],
        ]);
    }

    public function showRequirement(Request $request, TestProject $testProject, Requirement $requirement): Response
    {
        Gate::authorize(Ability::ViewRequirements->value, $testProject);

        $requirement->load([
            'requirementSpec',
            'versions.author',
            'versions.updater',
            'versions.coverages.testCaseVersion.testCase.testProject',
        ]);

        $asked = $request->has('version');

        $shown = $asked
            ? $requirement->versions->firstWhere('version', $request->integer('version'))
            : $requirement->versions->last();

        abort_if($asked && $shown === null, 404);

        return $this->page($testProject, [
            'selected' => [
                'type' => 'requirement',
                'requirement' => [
                    'id' => $requirement->id,
                    'name' => $requirement->name,
                    'doc_id' => $requirement->doc_id,
                    'requirement_spec_id' => $requirement->requirement_spec_id,
                    'spec_name' => $requirement->requirementSpec->name,
                    'versions' => $requirement->versions
                        ->map(fn (RequirementVersion $version): array => [
                            'id' => $version->id,
                            'version' => $version->version,
                            'is_open' => $version->is_open,
                        ])
                        ->all(),
                    'version' => $shown === null ? null : $this->version($shown, $testProject),
                    'watching_id' => $requirement->monitors()
                        ->where('user_id', $request->user()?->getKey())
                        ->value('id'),
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function version(RequirementVersion $version, TestProject $project): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'scope' => $version->scope,
            'status' => $version->status->value,
            'type' => $version->type->value,
            'expected_coverage' => $version->expected_coverage,
            'is_open' => $version->is_open,
            'author' => $version->author?->name,
            'updater' => $version->updater?->name,
            'coverages' => $version->coverages->map(fn (RequirementCoverage $coverage): array => [
                'id' => $coverage->id,
                'test_case_id' => $coverage->testCaseVersion->test_case_id,
                'test_case_version_id' => $coverage->test_case_version_id,
                'test_case_version' => $coverage->testCaseVersion->version,
                'full_external_id' => $coverage->testCaseVersion->testCase->fullExternalId(),
                'name' => $coverage->testCaseVersion->testCase->name,
            ])->all(),
            'coverable' => $this->coverableCases($project, $version),
        ];
    }

    /**
     * @return list<array{id: int, test_case_id: int, version: int, full_external_id: string, name: string}>
     */
    private function coverableCases(TestProject $project, RequirementVersion $version): array
    {
        $linked = $version->coverages->pluck('test_case_version_id')->all();

        return TestCaseVersion::query()
            ->whereHas('testCase', fn ($query) => $query->where('test_project_id', $project->id))
            ->with('testCase.testProject')
            ->whereKeyNot($linked)
            ->orderBy('test_case_id')
            ->orderBy('version')
            ->get()
            ->map(fn (TestCaseVersion $caseVersion): array => [
                'id' => $caseVersion->id,
                'test_case_id' => $caseVersion->test_case_id,
                'version' => $caseVersion->version,
                'full_external_id' => $caseVersion->testCase->fullExternalId(),
                'name' => $caseVersion->testCase->name,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function page(TestProject $testProject, array $extra = []): Response
    {
        return Inertia::render('requirements/index', [
            'project' => [
                'id' => $testProject->id,
                'name' => $testProject->name,
                'prefix' => $testProject->prefix,
            ],
            'tree' => $this->tree($testProject),
            'can' => [
                'manage' => Gate::allows(Ability::ManageRequirements->value, $testProject),
                'unfreeze' => Gate::allows(Ability::UnfreezeRequirements->value, $testProject),
                'coverage' => Gate::allows(Ability::ManageRequirementCoverage->value, $testProject),
                'monitor' => Gate::allows(Ability::MonitorRequirements->value, $testProject),
            ],
            'statuses' => array_map(fn (RequirementStatus $status): array => [
                'value' => $status->value,
                'label' => $status->name,
            ], RequirementStatus::cases()),
            'types' => array_map(fn (RequirementType $type): array => [
                'value' => $type->value,
                'label' => $type->name,
            ], RequirementType::cases()),
            'selected' => null,
            ...$extra,
        ]);
    }

    /**
     * @return list<array{id: int, name: string, doc_id: string, children: list<array<string, mixed>>, requirements: list<array{id: int, name: string, doc_id: string}>}>
     */
    private function tree(TestProject $testProject): array
    {
        $requirements = Requirement::query()
            ->where('test_project_id', $testProject->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('requirement_spec_id');

        $node = function (RequirementSpec $spec) use (&$node, $requirements): array {
            return [
                'id' => $spec->id,
                'name' => $spec->name,
                'doc_id' => $spec->doc_id,
                'children' => $spec->children->map(fn (RequirementSpec $child): array => $node($child))->all(),
                'requirements' => ($requirements[$spec->id] ?? collect())
                    ->map(fn (Requirement $requirement): array => [
                        'id' => $requirement->id,
                        'name' => $requirement->name,
                        'doc_id' => $requirement->doc_id,
                    ])
                    ->values()
                    ->all(),
            ];
        };

        return RequirementSpec::treeFor($testProject)
            ->map($node)
            ->values()
            ->all();
    }
}
