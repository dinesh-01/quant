<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait TestPlanValidationRules
{
    /**
     * Rules shared by creating and editing a plan.
     *
     * Plan names are unique per project, not globally, so the rule has to be
     * told which project to look in. That matches the composite unique index on
     * `test_plans`, which the rule only exists to turn into a readable message.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function testPlanRules(int $projectId, ?int $ignorePlanId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('test_plans', 'name')
                    ->where('test_project_id', $projectId)
                    ->ignore($ignorePlanId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_open' => ['boolean'],
            'is_public' => ['boolean'],
        ];
    }

    /**
     * @return array{name: string, description: string|null, is_active: bool, is_open: bool, is_public: bool}
     */
    protected function testPlanAttributes(): array
    {
        $description = $this->input('description');

        return [
            'name' => (string) $this->input('name'),
            'description' => is_string($description) && $description !== '' ? $description : null,
            'is_active' => $this->boolean('is_active'),
            'is_open' => $this->boolean('is_open'),
            'is_public' => $this->boolean('is_public'),
        ];
    }

    /**
     * A checked box in the browser submits "on". The boolean rule only
     * accepts 1/0, so fold the flags before validation.
     */
    protected function prepareCheckboxBooleans(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_open' => $this->boolean('is_open'),
            'is_public' => $this->boolean('is_public'),
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCheckboxBooleans();
    }
}
