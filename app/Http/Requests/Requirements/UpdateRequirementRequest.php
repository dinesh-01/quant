<?php

namespace App\Http\Requests\Requirements;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateRequirementRequest extends RequirementsFormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $requirement = $this->routeRequirement();

        return [
            'name' => ['required', 'string', 'max:255'],
            'doc_id' => [
                'required', 'string', 'max:64',
                Rule::unique('requirements', 'doc_id')
                    ->where('test_project_id', $requirement->test_project_id)
                    ->ignore($requirement),
            ],
        ];
    }

    /**
     * @return array{name: string, doc_id: string}
     */
    public function requirementAttributes(): array
    {
        return [
            'name' => $this->requiredString('name'),
            'doc_id' => $this->requiredString('doc_id'),
        ];
    }
}
