<?php

namespace App\Concerns;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rules shared by creating and editing a definition.
 *
 * Legacy validated a definition with two JavaScript checks — that the name and
 * label were not blank — and nothing on the server beyond a uniqueness lookup.
 * The consequences show up in its own data: a field could be saved with a type
 * that had no matching handler, with an empty option list for a dropdown, or
 * with a `valid_regexp` that no engine could compile, and none of it would
 * surface until somebody tried to fill the field in.
 *
 * Named for definitions to keep it distinct from `ValidatesCustomFieldValues`,
 * which is the other half: this checks the field, that one checks the answers.
 *
 * @phpstan-require-extends FormRequest
 */
trait CustomFieldValidationRules
{
    /**
     * @return array<string, mixed>
     */
    protected function customFieldDefinitionRules(?CustomField $ignoring = null): array
    {
        $type = $this->submittedType();

        return [
            /*
             * The stable identifier that an exported value carries, so it is
             * restricted to characters that survive a round trip through XML,
             * a query string and a column header without escaping.
             */
            'name' => [
                'required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('custom_fields', 'name')->ignore($ignoring?->getKey()),
            ],
            'label' => ['required', 'string', 'max:64'],
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'entity_type' => ['required', Rule::enum(CustomFieldEntity::class)],

            /*
             * A dropdown with nothing to choose from is not a field, it is a
             * dead control. Legacy would save one and then render an empty
             * `<select>` that could only ever submit nothing.
             */
            'options' => [
                $type?->hasOptions() === true ? 'required' : 'nullable',
                'array',
                $type?->hasOptions() === true ? 'min:1' : 'max:0',
            ],
            'options.*' => ['string', 'max:255', 'distinct'],

            'default_value' => array_filter([
                'nullable',
                'string',
                'max:4000',
                $type?->hasOptions() === true ? Rule::in($this->submittedOptions()) : null,
            ]),

            'pattern' => array_filter([
                'nullable',
                'string',
                'max:255',
                $type?->usesPattern() === true ? null : 'prohibited',
                $this->patternMustCompile(),
            ]),

            'minimum_length' => ['nullable', 'integer', 'min:0', 'max:4000'],
            'maximum_length' => ['nullable', 'integer', 'min:1', 'max:4000', 'gte:minimum_length'],
        ];
    }

    /**
     * A pattern that cannot be compiled would fail at validation time on every
     * screen the field appears on, so it is refused here instead.
     *
     * A pathological pattern is still possible — this is deliberately a
     * compilation check, not an analysis — but writing one takes
     * `manage_custom_fields`, which is a system ability held by administrators.
     */
    protected function patternMustCompile(): callable
    {
        return static function (string $attribute, mixed $value, callable $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            if (@preg_match($value, '') === false) {
                $fail(__('That is not a valid regular expression. It needs delimiters, as in /^[A-Z]{2}$/.'));
            }
        };
    }

    /**
     * @return array<string, string>
     */
    protected function customFieldMessages(): array
    {
        return [
            'name.regex' => __('The name may only contain letters, numbers, underscores and hyphens. It travels with exported values, so it is kept simple on purpose — the label is what people read.'),
            'options.required' => __('A field of this type needs at least one value to choose from.'),
            'options.max' => __('A field of this type has no values to choose from.'),
            'pattern.prohibited' => __('A pattern only means something for a text field.'),
        ];
    }

    /**
     * The attributes for `CreateCustomField` and `UpdateCustomField`.
     *
     * @return array<string, mixed>
     */
    protected function customFieldAttributes(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'label' => $validated['label'],
            'type' => $validated['type'],
            'entity_type' => $validated['entity_type'],
            'options' => $validated['options'] ?? null,
            'default_value' => $this->emptyToNull($validated['default_value'] ?? null),
            'pattern' => $this->emptyToNull($validated['pattern'] ?? null),
            'minimum_length' => $validated['minimum_length'] ?? null,
            'maximum_length' => $validated['maximum_length'] ?? null,
        ];
    }

    private function emptyToNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * The type being submitted, which most of the rules above depend on.
     */
    private function submittedType(): ?CustomFieldType
    {
        $type = $this->input('type');

        return is_string($type) ? CustomFieldType::tryFrom($type) : null;
    }

    /**
     * @return list<string>
     */
    private function submittedOptions(): array
    {
        $options = $this->input('options');

        if (! is_array($options)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $option): string => (string) $option, $options));
    }
}
