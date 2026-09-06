<?php

namespace App\Http\Requests\CustomFields;

use App\Models\TestProject;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The whole of a project's custom field assignment, as the screen posts it.
 *
 * One payload rather than the four separate actions legacy's screen had
 * (`doAssign`, `doUnassign`, `doReorder`, `doBooleanMgmt`), which between them
 * could leave the list in a state the operator had not asked for: the reorder
 * action wrote the order and location of *every* linked field, not just the
 * ones that had been touched.
 */
class ProjectCustomFieldsRequest extends FormRequest
{
    /**
     * `fields` may be absent, which means "none": a form with no rows left in
     * it posts nothing at all, and treating that as a missing field would make
     * removing the last one impossible. The same reasoning as
     * `TestCaseKeywordsRequest`, and the same consequence — this endpoint
     * states the whole list, so what is left out is removed.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fields' => ['array'],
            'fields.*.id' => ['required', 'integer', 'exists:custom_fields,id'],
            'fields.*.is_active' => ['boolean'],
            'fields.*.sort_order' => ['integer', 'min:0', 'max:9999'],
            'fields.*.required_on_design' => ['boolean'],
            'fields.*.required_on_execution' => ['boolean'],
        ];
    }

    /**
     * The desired state, keyed by field id, as `SetProjectCustomFields` wants.
     *
     * @return array<int, array{is_active: bool, sort_order: int, required_on_design: bool, required_on_execution: bool}>
     */
    public function settings(): array
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = $this->validated()['fields'] ?? [];

        $settings = [];

        foreach ($fields as $field) {
            $settings[(int) $field['id']] = [
                'is_active' => (bool) ($field['is_active'] ?? true),
                'sort_order' => (int) ($field['sort_order'] ?? 0),
                'required_on_design' => (bool) ($field['required_on_design'] ?? false),
                'required_on_execution' => (bool) ($field['required_on_execution'] ?? false),
            ];
        }

        return $settings;
    }

    /**
     * The bound project, narrowed for static analysis.
     */
    public function routeTestProject(): TestProject
    {
        $project = $this->route('testProject');

        abort_unless($project instanceof TestProject, 404);

        return $project;
    }
}
