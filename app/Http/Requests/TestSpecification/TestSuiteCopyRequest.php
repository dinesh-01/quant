<?php

namespace App\Http\Requests\TestSpecification;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TestSuiteCopyRequest extends SpecificationFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Copying may cross projects, so `test_project_id` is accepted and the
     * parent, when given, must belong to it. Omitting both copies the suite to
     * the root of its own project.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $targetProjectId = $this->optionalId('test_project_id')
            ?? $this->routeTestSuite()->test_project_id;

        return [
            'test_project_id' => ['nullable', 'integer', Rule::exists('test_projects', 'id')],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('test_suites', 'id')->where('test_project_id', $targetProjectId),
            ],
        ];
    }
}
