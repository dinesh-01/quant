<?php

namespace App\Http\Requests\CustomFields;

use App\Concerns\CustomFieldValidationRules;
use App\Models\CustomField;
use Illuminate\Foundation\Http\FormRequest;

class CustomFieldUpdateRequest extends FormRequest
{
    use CustomFieldValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->customFieldDefinitionRules($this->routeCustomField());
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

    /**
     * The bound field, narrowed for static analysis.
     */
    public function routeCustomField(): CustomField
    {
        $field = $this->route('customField');

        abort_unless($field instanceof CustomField, 404);

        return $field;
    }
}
