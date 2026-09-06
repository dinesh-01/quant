<?php

namespace App\Http\Requests\TestSpecification;

use Illuminate\Contracts\Validation\ValidationRule;

class TestCaseUpdateRequest extends SpecificationFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Only the name lives on the case itself. Everything else a user thinks of
     * as "the test case" belongs to a version and goes through
     * TestCaseVersionUpdateRequest.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array{name: string}
     */
    public function caseAttributes(): array
    {
        return ['name' => $this->requiredString('name')];
    }
}
