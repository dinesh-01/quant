<?php

namespace App\Http\Requests\Roles;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards role deletion behind typing the role's name, as the project and plan
 * screens do.
 *
 * `DeleteRole` already refuses a role anyone is using, so this is the second
 * lock rather than the only one — but the abilities a role granted are not
 * recorded anywhere once it is gone, so recreating one deleted by mistake means
 * reconstructing it from memory.
 */
class RoleDeleteRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirm_name' => ['required', 'string', Rule::in([$this->routeRole()->name])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_name.in' => 'Type the role name exactly to confirm deletion.',
        ];
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
