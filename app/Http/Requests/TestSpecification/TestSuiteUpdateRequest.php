<?php

namespace App\Http\Requests\TestSpecification;

use App\Concerns\TestSuiteValidationRules;
use App\Concerns\ValidatesCustomFieldValues;
use Illuminate\Contracts\Validation\ValidationRule;

class TestSuiteUpdateRequest extends SpecificationFormRequest
{
    use TestSuiteValidationRules;
    use ValidatesCustomFieldValues;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $suite = $this->routeTestSuite();

        return [
            'name' => [
                'required', 'string', 'max:255',
                $this->siblingNameRule($suite->test_project_id, $suite->parent_id, $suite->id),
            ],
            'description' => ['nullable', 'string'],
            ...$this->customFieldRulesFor($suite),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseCustomFieldInput();
    }

    /**
     * @return array{name: string, description: string|null}
     */
    public function suiteAttributes(): array
    {
        return [
            'name' => $this->requiredString('name'),
            'description' => $this->optionalText('description'),
        ];
    }
}
