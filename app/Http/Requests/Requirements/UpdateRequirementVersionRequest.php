<?php

namespace App\Http\Requests\Requirements;

use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateRequirementVersionRequest extends RequirementsFormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scope' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(RequirementStatus::class)],
            'type' => ['required', Rule::enum(RequirementType::class)],
            'expected_coverage' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array{scope: string|null, status: RequirementStatus, type: RequirementType, expected_coverage: int}
     */
    public function versionAttributes(): array
    {
        return [
            'scope' => $this->optionalText('scope'),
            'status' => $this->requiredEnum('status', RequirementStatus::class),
            'type' => $this->requiredEnum('type', RequirementType::class),
            'expected_coverage' => $this->integer('expected_coverage'),
        ];
    }
}
