<?php

namespace App\Http\Requests\Users;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UserPasswordRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * The same `Password::defaults()` policy as every other place a password is
     * chosen. Notably there is no `current_password` rule: the administrator
     * does not know the password they are replacing, which is the entire reason
     * this route exists.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->passwordRules(),
        ];
    }

    public function newPassword(): string
    {
        return (string) $this->input('password');
    }
}
