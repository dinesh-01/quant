<?php

namespace App\Http\Requests\Requirements;

use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreRequirementRequest extends RequirementsFormRequest
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
                Rule::unique('requirements', 'doc_id')->where('test_project_id', $spec->test_project_id),
            ],
            'scope' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(RequirementStatus::class)],
            'type' => ['required', Rule::enum(RequirementType::class)],
            'expected_coverage' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array{name: string, doc_id: string, scope: string|null, status: RequirementStatus, type: RequirementType, expected_coverage: int}
     */
    public function requirementAttributes(): array
    {
        return [
            'name' => $this->requiredString('name'),
            'doc_id' => $this->requiredString('doc_id'),
            'scope' => $this->optionalText('scope'),
            'status' => $this->requiredEnum('status', RequirementStatus::class),
            'type' => $this->requiredEnum('type', RequirementType::class),
            'expected_coverage' => $this->integer('expected_coverage'),
        ];
    }
}
