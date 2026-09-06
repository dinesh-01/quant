<?php

namespace App\Concerns;

use App\Enums\Ability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait RoleValidationRules
{
    /**
     * Rules shared by creating and editing a role.
     *
     * `abilities` is validated against the enum, so a value that no longer has
     * a case is rejected at the boundary rather than stored and thrown by
     * `AsEnumCollection` on the next read.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function roleRules(?int $ignoreRoleId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->ignore($ignoreRoleId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'abilities' => ['array'],
            'abilities.*' => [Rule::enum(Ability::class)],
            'is_super_admin' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * @return array{name: string, description: string|null, abilities: list<Ability>, is_super_admin: bool, is_default: bool}
     */
    protected function roleAttributes(): array
    {
        $description = $this->input('description');
        $submitted = $this->input('abilities', []);

        return [
            'name' => (string) $this->input('name'),
            'description' => is_string($description) && $description !== '' ? $description : null,
            'abilities' => array_values(array_map(
                fn (mixed $value): Ability => Ability::from((string) $value),
                is_array($submitted) ? $submitted : [],
            )),
            'is_super_admin' => $this->boolean('is_super_admin'),
            'is_default' => $this->boolean('is_default'),
        ];
    }
}
