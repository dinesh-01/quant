<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait TestProjectValidationRules
{
    /**
     * Rules shared by creating and editing a project.
     *
     * Both `name` and `prefix` carry unique indexes, so the rules here only
     * turn a race into a friendly message rather than being the constraint.
     * The prefix is uppercased in `prepareForValidation()` before uniqueness is
     * checked, so `qa` and `QA` cannot both be taken.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function testProjectRules(?int $ignoreProjectId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('test_projects', 'name')->ignore($ignoreProjectId),
            ],
            'prefix' => [
                'required', 'string', 'max:16', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('test_projects', 'prefix')->ignore($ignoreProjectId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_public' => ['boolean'],
        ];
    }

    /**
     * @return array{name: string, prefix: string, description: string|null, is_active: bool, is_public: bool}
     */
    protected function testProjectAttributes(): array
    {
        $description = $this->input('description');

        return [
            'name' => (string) $this->input('name'),
            'prefix' => (string) $this->input('prefix'),
            'description' => is_string($description) && $description !== '' ? $description : null,
            'is_active' => $this->boolean('is_active'),
            'is_public' => $this->boolean('is_public'),
        ];
    }

    /**
     * Fold the prefix to upper case before anything looks at it.
     *
     * `PREFIX-N` is rendered upper case everywhere, so accepting `qa` would
     * create a project whose ids read `QA-1` while its stored prefix reads
     * `qa`, and would let a second project take `QA` past the unique index.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($prefix = $this->input('prefix'))) {
            $this->merge(['prefix' => mb_strtoupper(trim($prefix))]);
        }

        /*
         * A checked box in the browser submits "on". The boolean rule only
         * accepts 1/0, so fold it here or create looks like it failed and
         * stores nothing.
         */
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_public' => $this->boolean('is_public'),
        ]);
    }
}
