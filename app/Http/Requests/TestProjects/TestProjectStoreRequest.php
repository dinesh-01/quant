<?php

namespace App\Http\Requests\TestProjects;

use App\Concerns\TestProjectValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TestProjectStoreRequest extends FormRequest
{
    use TestProjectValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->testProjectRules();
    }

    /**
     * @return array{name: string, prefix: string, description: string|null, is_active: bool, is_public: bool}
     */
    public function projectAttributes(): array
    {
        return $this->testProjectAttributes();
    }
}
