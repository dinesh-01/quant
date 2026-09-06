<?php

namespace App\Http\Controllers\CustomFields;

use App\Actions\CustomFields\ResolveCustomFields;
use App\Actions\CustomFields\SetProjectCustomFields;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomFields\ProjectCustomFieldsRequest;
use App\Models\CustomField;
use App\Models\TestProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Which of the catalogue's fields one project uses.
 *
 * `assign_custom_fields` is project-scoped, so a project lead decides what
 * their own team records without being able to touch the shared definitions.
 */
class ProjectCustomFieldController extends Controller
{
    public function __construct(private readonly ResolveCustomFields $fields) {}

    public function index(Request $request, TestProject $testProject): Response
    {
        $user = $this->actingUser($request);

        Gate::forUser($user)->authorize(Ability::AssignCustomFields->value, $testProject);

        $assigned = $testProject->customFields()->get();
        $answerCounts = $this->fields->answerCountsIn($testProject, $assigned);

        /*
         * The catalogue minus what is already here, so the screen can offer the
         * rest without a second thought about duplicates.
         */
        $available = CustomField::query()
            ->whereNotIn('id', $assigned->modelKeys())
            ->alphabetically()
            ->get();

        return Inertia::render('custom-fields/project', [
            'project' => [
                'id' => $testProject->id,
                'name' => $testProject->name,
            ],
            'assigned' => array_values($assigned
                ->map(fn (CustomField $field): array => [
                    ...$this->fieldProps($field),
                    'is_active' => $field->isActiveIn(),
                    'sort_order' => $field->sortOrderIn(),
                    'required_on_design' => $field->isRequiredOnDesign(),
                    'required_on_execution' => $field->isRequiredOnExecution(),
                    /* What removing it would cost, which is not recoverable. */
                    'answers_count' => $answerCounts[$field->id] ?? 0,
                ])
                ->all()),
            'available' => array_values($available
                ->map(fn (CustomField $field): array => $this->fieldProps($field))
                ->all()),
        ]);
    }

    public function update(
        ProjectCustomFieldsRequest $request,
        TestProject $testProject,
        SetProjectCustomFields $setProjectCustomFields,
    ): RedirectResponse {
        $setProjectCustomFields($this->actingUser($request), $testProject, $request->settings());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom fields updated.')]);

        return to_route('projects.custom-fields.index', $testProject);
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldProps(CustomField $field): array
    {
        return [
            'id' => $field->id,
            'name' => $field->name,
            'label' => $field->label,
            'type_label' => $field->type->label(),
            'entity_type' => $field->entity_type->value,
            'entity_label' => $field->entity_type->label(),
        ];
    }
}
