<?php

namespace App\Http\Requests\TestSpecification;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TestCaseReorderRequest extends SpecificationFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $suite = $this->routeTestSuite();

        return [
            'order' => ['required', 'array'],
            'order.*' => [
                'integer',
                Rule::exists('test_cases', 'id')->where('test_suite_id', $suite->id),
            ],
        ];
    }
}
