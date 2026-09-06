<?php

namespace App\Http\Requests\TestSpecification;

use App\Enums\TestCaseExecutionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TestCaseStepRequest extends SpecificationFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Used for creating and editing a step alike. `sort_order` is absent on
     * purpose: position is only ever changed through the reorder action, which
     * renumbers every sibling in one pass.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'actions' => ['nullable', 'string'],
            'expected_results' => ['nullable', 'string'],
            'execution_type' => ['required', Rule::enum(TestCaseExecutionType::class)],
        ];
    }

    /**
     * @return array{actions: string|null, expected_results: string|null, execution_type: TestCaseExecutionType}
     */
    public function stepAttributes(): array
    {
        return [
            'actions' => $this->optionalText('actions'),
            'expected_results' => $this->optionalText('expected_results'),
            'execution_type' => $this->requiredEnum('execution_type', TestCaseExecutionType::class),
        ];
    }
}
