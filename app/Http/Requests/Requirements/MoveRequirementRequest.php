<?php

namespace App\Http\Requests\Requirements;

use App\Models\RequirementSpec;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class MoveRequirementRequest extends RequirementsFormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $requirement = $this->routeRequirement();

        return [
            'requirement_spec_id' => [
                'required', 'integer',
                Rule::exists('requirement_specs', 'id')->where('test_project_id', $requirement->test_project_id),
            ],
        ];
    }

    public function targetSpec(): RequirementSpec
    {
        return RequirementSpec::query()->findOrFail($this->integer('requirement_spec_id'));
    }
}
