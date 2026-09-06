<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait PlatformValidationRules
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function platformRules(int $projectId, ?int $ignorePlatformId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('platforms', 'name')
                    ->where('test_project_id', $projectId)
                    ->ignore($ignorePlatformId),
            ],
            'notes' => ['nullable', 'string'],
            'enable_on_design' => ['boolean'],
            'enable_on_execution' => ['boolean'],
            'is_open' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }

        $this->merge([
            'enable_on_design' => $this->boolean('enable_on_design'),
            'enable_on_execution' => $this->boolean('enable_on_execution'),
            'is_open' => $this->boolean('is_open'),
        ]);
    }

    /**
     * @return array{name: string, notes: string|null, enable_on_design: bool, enable_on_execution: bool, is_open: bool}
     */
    protected function platformAttributes(): array
    {
        $notes = $this->input('notes');

        return [
            'name' => (string) $this->input('name'),
            'notes' => is_string($notes) && trim($notes) !== '' ? $notes : null,
            'enable_on_design' => $this->boolean('enable_on_design'),
            'enable_on_execution' => $this->boolean('enable_on_execution'),
            'is_open' => $this->boolean('is_open'),
        ];
    }
}
