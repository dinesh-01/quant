<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait KeywordValidationRules
{
    /**
     * Rules shared by creating and editing a keyword.
     *
     * The name is unique per project rather than globally, matching the
     * composite index the rule exists to turn into a readable message. MySQL
     * compares it case-insensitively under this project's collation, so the
     * rule agrees with the database without a `lower()` anywhere.
     *
     * 100 characters matches legacy. Legacy also refused `"` and `,`, which was
     * not a rule about keywords: it moved assignments around as
     * comma-separated strings in query strings. Nothing here does.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function keywordRules(int $projectId, ?int $ignoreKeywordId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('keywords', 'name')
                    ->where('test_project_id', $projectId)
                    ->ignore($ignoreKeywordId),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Trim the name before anything looks at it, so the uniqueness rule and the
     * stored value cannot disagree — otherwise ` smoke` passes a check that
     * `smoke` would have failed, and the list ends up with both.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }

    /**
     * @return array{name: string, notes: string|null}
     */
    protected function keywordAttributes(): array
    {
        $notes = $this->input('notes');

        return [
            'name' => (string) $this->input('name'),
            'notes' => is_string($notes) && trim($notes) !== '' ? $notes : null,
        ];
    }
}
