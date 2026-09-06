<?php

namespace App\Http\Requests\Users;

use App\Concerns\UserValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
{
    use UserValidationRules;

    /**
     * The initial password is no longer collected. CreateUser stores a random
     * one and emails a reset link.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->userRules();
    }

    /**
     * @return array{name: string, email: string, role_id: int|null, is_active: bool, expires_at: string|null}
     */
    public function newUserAttributes(): array
    {
        return $this->userAttributes();
    }
}
