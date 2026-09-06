<?php

namespace App\Http\Requests\RoleAssignments;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A member is named by email rather than picked from a list of every user.
 *
 * A select of all users would have to be sent on every page load, growing with
 * the user directory and handing the whole list to anyone who can assign a
 * role. Email is what an assigner already knows. A searching picker can replace
 * this once Phase 1b's user management exists to search against.
 */
class RoleAssignmentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_email' => ['required', 'string', 'email', 'exists:users,email'],
            'role_id' => [
                'required', 'integer',
                Rule::exists('roles', 'id')->where('is_super_admin', false),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_email.exists' => 'No user is registered with that email address.',
            'role_id.exists' => 'That role cannot be given for a single project or plan.',
        ];
    }

    public function member(): User
    {
        return User::query()
            ->where('email', (string) $this->validated('user_email'))
            ->firstOrFail();
    }

    public function role(): Role
    {
        return Role::query()
            ->whereKey((int) $this->validated('role_id'))
            ->firstOrFail();
    }
}
