<?php

namespace App\Http\Requests\TestSpecification;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TestCaseCopyRequest extends SpecificationFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Any existing suite is a valid target, in this project or another. The
     * copy takes a fresh external id from whichever project it lands in, and
     * CopyTestCase authorizes both ends.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'test_suite_id' => ['required', 'integer', Rule::exists('test_suites', 'id')],
        ];
    }
}
