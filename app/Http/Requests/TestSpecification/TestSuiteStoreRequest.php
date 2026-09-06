<?php

namespace App\Http\Requests\TestSpecification;

use App\Concerns\TestSuiteValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TestSuiteStoreRequest extends SpecificationFormRequest
{
    use TestSuiteValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->routeTestProject();

        return [
            'name' => [
                'required', 'string', 'max:255',
                $this->siblingNameRule($project->id, $this->optionalId('parent_id')),
            ],
            'description' => ['nullable', 'string'],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('test_suites', 'id')->where('test_project_id', $project->id),
            ],
        ];
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

    public function parentSuiteId(): ?int
    {
        return $this->optionalId('parent_id');
    }
}
