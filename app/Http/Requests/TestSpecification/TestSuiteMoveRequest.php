<?php

namespace App\Http\Requests\TestSpecification;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TestSuiteMoveRequest extends SpecificationFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * A null `parent_id` moves the suite to the project root. The rule only
     * checks the target exists in the same project; refusing a move into the
     * suite's own subtree, or one that would breach the nesting limit, is
     * MoveTestSuite's job because both need tree queries.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $suite = $this->routeTestSuite();

        return [
            'parent_id' => [
                'present', 'nullable', 'integer',
                Rule::exists('test_suites', 'id')->where('test_project_id', $suite->test_project_id),
            ],
        ];
    }
}
