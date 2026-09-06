<?php

namespace App\Http\Requests\Requirements;

use App\Models\RequirementSpec;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class MoveRequirementSpecRequest extends RequirementsFormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $spec = $this->routeRequirementSpec();

        return [
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('requirement_specs', 'id')->where('test_project_id', $spec->test_project_id),
            ],
        ];
    }

    public function parentSpec(): ?RequirementSpec
    {
        $id = $this->optionalId('parent_id');

        return $id === null ? null : RequirementSpec::query()->findOrFail($id);
    }
}
