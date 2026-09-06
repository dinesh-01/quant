<?php

namespace App\Http\Requests\TestSpecification;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TestCaseMoveRequest extends SpecificationFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The target suite must be in the case's own project. A case keeps its
     * `PREFIX-N` across a move so existing links stay valid, and that number is
     * only unique within one project — copying is the cross-project operation.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $case = $this->routeTestCase();

        return [
            'test_suite_id' => [
                'required', 'integer',
                Rule::exists('test_suites', 'id')->where('test_project_id', $case->test_project_id),
            ],
        ];
    }
}
