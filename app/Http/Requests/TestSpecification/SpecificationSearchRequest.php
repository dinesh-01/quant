<?php

namespace App\Http\Requests\TestSpecification;

use Illuminate\Contracts\Validation\ValidationRule;

class SpecificationSearchRequest extends SpecificationFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * A single character is allowed on purpose: in a young project `QA-3` is a
     * real identifier, and a one-character term is not a risk because the scope
     * escapes wildcards and the query is capped. Requiring two characters would
     * make external ids below 10 unsearchable.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }
}
