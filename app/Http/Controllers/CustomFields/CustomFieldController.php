<?php

namespace App\Http\Controllers\CustomFields;

use App\Actions\CustomFields\CreateCustomField;
use App\Actions\CustomFields\DeleteCustomField;
use App\Actions\CustomFields\UpdateCustomField;
use App\Enums\Ability;
use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomFields\CustomFieldStoreRequest;
use App\Http\Requests\CustomFields\CustomFieldUpdateRequest;
use App\Models\CustomField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The application-wide catalogue of custom field definitions.
 *
 * `view_custom_fields` and `manage_custom_fields` are system abilities, so
 * these screens need a global role: one definition is shared by every project
 * that enables it. Which projects those are is a separate screen and a separate
 * right — see `ProjectCustomFieldController`.
 */
class CustomFieldController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->actingUser($request);

        Gate::forUser($user)->authorize(Ability::ViewCustomFields->value);

        /*
         * Two counts per row, because they are what a reader of this list
         * actually needs: how far the field has spread, and how much data
         * would go with it. One aggregate query each, not one per row.
         */
        $fields = CustomField::query()
            ->withCount(['testProjects', 'values'])
            ->alphabetically()
            ->get()
            ->map(fn (CustomField $field): array => [
                'id' => $field->id,
                'name' => $field->name,
                'label' => $field->label,
                'type' => $field->type->value,
                'type_label' => $field->type->label(),
                'entity_type' => $field->entity_type->value,
                'entity_label' => $field->entity_type->label(),
                'projects_count' => (int) $field->test_projects_count,
                'answers_count' => (int) $field->values_count,
            ])
            ->all();

        return Inertia::render('custom-fields/index', [
            'fields' => array_values($fields),
            'can' => [
                'manage' => Gate::forUser($user)->allows(Ability::ManageCustomFields->value),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::forUser($this->actingUser($request))->authorize(Ability::ManageCustomFields->value);

        return Inertia::render('custom-fields/create', [
            'types' => $this->typeProps(),
            'entities' => $this->entityProps(),
        ]);
    }

    public function store(CustomFieldStoreRequest $request, CreateCustomField $createCustomField): RedirectResponse
    {
        $createCustomField($this->actingUser($request), $request->definition());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom field created.')]);

        return to_route('custom-fields.index');
    }

    public function edit(Request $request, CustomField $customField): Response
    {
        Gate::forUser($this->actingUser($request))->authorize(Ability::ManageCustomFields->value);

        return Inertia::render('custom-fields/edit', [
            'field' => [
                'id' => $customField->id,
                'name' => $customField->name,
                'label' => $customField->label,
                'type' => $customField->type->value,
                'entity_type' => $customField->entity_type->value,
                'options' => $customField->options(),
                'default_value' => $customField->default_value,
                'pattern' => $customField->pattern,
                'minimum_length' => $customField->minimum_length,
                'maximum_length' => $customField->maximum_length,
                'projects_count' => $customField->testProjects()->count(),
                'answers_count' => $customField->values()->count(),
                /*
                 * What the form disables rather than what it warns about: an
                 * answered field's type and binding are frozen, because the
                 * answers were validated against how it is defined today.
                 */
                'is_answered' => $customField->isAnswered(),
            ],
            'types' => $this->typeProps(),
            'entities' => $this->entityProps(),
        ]);
    }

    public function update(
        CustomFieldUpdateRequest $request,
        CustomField $customField,
        UpdateCustomField $updateCustomField,
    ): RedirectResponse {
        $updateCustomField($this->actingUser($request), $customField, $request->definition());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom field updated.')]);

        return to_route('custom-fields.index');
    }

    public function destroy(
        Request $request,
        CustomField $customField,
        DeleteCustomField $deleteCustomField,
    ): RedirectResponse {
        $label = $customField->label;

        $deleteCustomField($this->actingUser($request), $customField);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('":label" deleted.', ['label' => $label]),
        ]);

        return to_route('custom-fields.index');
    }

    /**
     * The type catalogue, with what each type needs, so the form can show the
     * option editor and the length and pattern inputs only where they apply.
     *
     * @return list<array<string, mixed>>
     */
    private function typeProps(): array
    {
        return array_map(fn (CustomFieldType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'has_options' => $type->hasOptions(),
            'is_multi_value' => $type->isMultiValue(),
            'uses_length_limits' => $type->usesLengthLimits(),
            'uses_pattern' => $type->usesPattern(),
        ], CustomFieldType::cases());
    }

    /**
     * @return list<array<string, string>>
     */
    private function entityProps(): array
    {
        return array_map(fn (CustomFieldEntity $entity): array => [
            'value' => $entity->value,
            'label' => $entity->label(),
        ], CustomFieldEntity::cases());
    }
}
