<?php

namespace App\Http\Requests\Roles;

use App\Concerns\RoleValidationRules;
use App\Enums\Ability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RoleStoreRequest extends FormRequest
{
    use RoleValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->roleRules();
    }

    /**
     * @return array{name: string, description: string|null, abilities: list<Ability>, is_super_admin: bool, is_default: bool}
     */
    public function newRoleAttributes(): array
    {
        return $this->roleAttributes();
    }
}
