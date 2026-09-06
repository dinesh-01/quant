<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait UserValidationRules
{
    use ProfileValidationRules;

    /**
     * Rules shared by creating and editing an account.
     *
     * `role_id` is nullable because holding no global role is a real state, not
     * an incomplete one: a user can be given roles on individual projects
     * instead and is denied everything elsewhere. A super-admin role is
     * allowed here, unlike on the project and plan member screens, because
     * granting one globally is the only way it takes effect at all.
     *
     * A past `expires_at` is accepted deliberately — it is how an
     * administrator revokes access as of today rather than at some future
     * date. `PreventsAdministratorLockout` is what stops them doing it to
     * themselves.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function userRules(?int $ignoreUserId = null): array
    {
        return [
            ...$this->profileRules($ignoreUserId),
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'is_active' => ['boolean'],
            'expires_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array{name: string, email: string, role_id: int|null, is_active: bool, expires_at: string|null}
     */
    protected function userAttributes(): array
    {
        $roleId = $this->input('role_id');
        $expiresAt = $this->input('expires_at');

        return [
            'name' => (string) $this->input('name'),
            'email' => (string) $this->input('email'),
            'role_id' => is_numeric($roleId) ? (int) $roleId : null,
            'is_active' => $this->boolean('is_active'),
            'expires_at' => is_string($expiresAt) && $expiresAt !== '' ? $expiresAt : null,
        ];
    }
}
