<?php

namespace App\Http\Requests\Roles;

use App\Concerns\RoleValidationRules;
use App\Enums\Ability;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RoleUpdateRequest extends FormRequest
{
    use RoleValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->roleRules($this->routeRole()->getKey());
    }

    /**
     * @return array{name: string, description: string|null, abilities: list<Ability>, is_super_admin: bool, is_default: bool}
     */
    public function editedRoleAttributes(): array
    {
        return $this->roleAttributes();
    }

    /**
     * The bound role, narrowed for static analysis.
     */
    public function routeRole(): Role
    {
        $role = $this->route('role');

        abort_unless($role instanceof Role, 404);

        return $role;
    }
}
