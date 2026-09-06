<?php

namespace App\Http\Requests\CustomFields;

use App\Concerns\CustomFieldValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class CustomFieldStoreRequest extends FormRequest
{
    use CustomFieldValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->customFieldDefinitionRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->customFieldMessages();
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->customFieldAttributes();
    }
}
