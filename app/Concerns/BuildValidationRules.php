<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait BuildValidationRules
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function buildRules(int $planId, ?int $ignoreBuildId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('builds', 'name')
                    ->where('test_plan_id', $planId)
                    ->ignore($ignoreBuildId),
            ],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_open' => ['boolean'],
            'release_date' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_open' => $this->boolean('is_open'),
        ]);
    }

    /**
     * @return array{name: string, notes: string|null, is_active: bool, is_open: bool, release_date: string|null}
     */
    protected function buildAttributes(): array
    {
        $notes = $this->input('notes');
        $releaseDate = $this->input('release_date');

        return [
            'name' => (string) $this->input('name'),
            'notes' => is_string($notes) && trim($notes) !== '' ? $notes : null,
            'is_active' => $this->boolean('is_active'),
            'is_open' => $this->boolean('is_open'),
            'release_date' => is_string($releaseDate) && $releaseDate !== '' ? $releaseDate : null,
        ];
    }
}
