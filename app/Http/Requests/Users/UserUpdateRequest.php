<?php

namespace App\Http\Requests\Users;

use App\Concerns\UserValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    use UserValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->userRules($this->routeUser()->getKey());
    }

    /**
     * An absent `role_id` means "leave the role alone", not "take it away".
     *
     * The edit form leaves the field out entirely for an actor who holds
     * `manage_users` but not `assign_global_roles`. Reading that omission as an
     * empty value would make every such edit either strip the subject's role or
     * be refused as an unauthorized role change. A field that is present but
     * empty still means no global role.
     *
     * @return array{name: string, email: string, role_id: int|null, is_active: bool, expires_at: string|null}
     */
    public function editedUserAttributes(): array
    {
        $attributes = $this->userAttributes();

        if (! $this->has('role_id')) {
            $attributes['role_id'] = $this->routeUser()->role_id;
        }

        return $attributes;
    }

    /**
     * The bound user, narrowed for static analysis.
     */
    public function routeUser(): User
    {
        $user = $this->route('user');

        abort_unless($user instanceof User, 404);

        return $user;
    }
}
