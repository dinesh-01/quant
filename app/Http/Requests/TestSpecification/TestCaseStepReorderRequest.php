<?php

namespace App\Http\Requests\TestSpecification;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TestCaseStepReorderRequest extends SpecificationFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $version = $this->routeTestCaseVersion();

        return [
            'order' => ['required', 'array'],
            'order.*' => [
                'integer',
                Rule::exists('test_case_steps', 'id')->where('test_case_version_id', $version->id),
            ],
        ];
    }
}
