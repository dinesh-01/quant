<?php

namespace App\Http\Requests\TestSpecification;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TestSuiteReorderRequest extends SpecificationFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The ids only have to exist in the project here. ReorderTestSuites checks
     * that they are exactly the children of the given parent, which is the
     * check that matters and cannot be expressed as a rule.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->routeTestProject();

        return [
            'parent_id' => [
                'present', 'nullable', 'integer',
                Rule::exists('test_suites', 'id')->where('test_project_id', $project->id),
            ],
            'order' => ['required', 'array'],
            'order.*' => [
                'integer',
                Rule::exists('test_suites', 'id')->where('test_project_id', $project->id),
            ],
        ];
    }
}
