<?php

namespace App\Http\Requests\Requirements;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateRequirementSpecRequest extends RequirementsFormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $spec = $this->routeRequirementSpec();

        return [
            'name' => ['required', 'string', 'max:255'],
            'doc_id' => [
                'required', 'string', 'max:64',
                Rule::unique('requirement_specs', 'doc_id')
                    ->where('test_project_id', $spec->test_project_id)
                    ->ignore($spec),
            ],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{name: string, doc_id: string, description: string|null}
     */
    public function specAttributes(): array
    {
        return [
            'name' => $this->requiredString('name'),
            'doc_id' => $this->requiredString('doc_id'),
            'description' => $this->optionalText('description'),
        ];
    }
}
