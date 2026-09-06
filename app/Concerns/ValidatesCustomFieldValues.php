<?php

namespace App\Concerns;

use App\Actions\CustomFields\ResolveCustomFields;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldSubject;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Turns custom field definitions into validation rules.
 *
 * This is the part legacy declared and never built. `valid_regexp` and
 * `length_min` were columns no code ever read; `length_max` reached the browser
 * as a `maxlength` attribute and stopped there; and the only checking that
 * happened at all was in `cfield_validation.js`, which covered four of the
 * thirteen types and skipped string, list, checkbox, radio, date and datetime
 * outright. Nothing checked anything on the server, so a hand-written POST
 * stored whatever it liked — a "numeric" field would happily hold `abc` — and
 * the value was then silently clipped to 255 characters on the way in.
 *
 * Rules are derived from the definition rather than written per screen, so a
 * field is checked the same way wherever it is filled in.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesCustomFieldValues
{
    private bool $customFieldsOnExecution = false;

    private bool $enforceCustomFieldRequired = true;

    /**
     * The rules for a subject's fields, keyed as the payload is nested.
     *
     * Values arrive as `custom_fields[{id}]`, which is worth contrasting with
     * legacy's flat `custom_field_{type}_{id}{suffix}` names: those had to be
     * parsed back out of the request by exploding on `_` and reading positions
     * 2 and 3, and the date types appended their own suffixes, so the index the
     * field id lived at moved depending on which screen had rendered it.
     *
     * @param  EloquentCollection<int, CustomField>  $fields
     * @return array<string, mixed>
     */
    protected function customFieldRules(
        EloquentCollection $fields,
        bool $onExecution = false,
        bool $enforceRequired = true,
    ): array {
        $this->customFieldsOnExecution = $onExecution;
        $this->enforceCustomFieldRequired = $enforceRequired;

        $rules = [
            'custom_fields' => ['sometimes', 'array'],
        ];

        foreach ($fields as $field) {
            $key = "custom_fields.{$field->id}";

            if ($field->type->isMultiValue()) {
                $rules[$key] = $this->multiValueRules($field);
                $rules["{$key}.*"] = [Rule::in($field->options())];

                continue;
            }

            $rules[$key] = $this->scalarRules($field);
        }

        /*
         * Anything else under `custom_fields` is a field this project has not
         * enabled, or one defined against another kind of thing. Rejecting
         * rather than ignoring means a wrong id is a visible error instead of
         * an answer that silently never appears.
         */
        $rules['custom_fields'][] = function (string $attribute, mixed $value, callable $fail) use ($fields): void {
            if (! is_array($value)) {
                return;
            }

            $unknown = array_diff(array_map('intval', array_keys($value)), $fields->modelKeys());

            if ($unknown !== []) {
                $fail(__('One of the fields submitted does not apply here.'));
            }
        };

        return $rules;
    }

    /**
     * @return array<int, mixed>
     */
    private function multiValueRules(CustomField $field): array
    {
        return [
            $this->requirednessOf($field),
            'array',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function scalarRules(CustomField $field): array
    {
        $rules = [$this->requirednessOf($field)];

        $rules[] = match ($field->type) {
            CustomFieldType::Numeric => 'integer',
            CustomFieldType::Float => 'numeric',
            CustomFieldType::Email => 'email',
            CustomFieldType::Date => 'date_format:'.CustomFieldType::DATE_FORMAT,
            CustomFieldType::DateTime => 'date_format:'.CustomFieldType::DATE_TIME_FORMAT,
            CustomFieldType::Dropdown, CustomFieldType::Radio => Rule::in($field->options()),
            default => 'string',
        };

        if ($field->type->usesLengthLimits()) {
            $rules[] = 'max:'.$field->maximumLength();

            if ($field->minimum_length !== null) {
                $rules[] = 'min:'.$field->minimum_length;
            }
        }

        if ($field->type->usesPattern() && $field->pattern !== null) {
            $rules[] = 'regex:'.$field->pattern;
        }

        return $rules;
    }

    /**
     * Whether an answer has to be given.
     *
     * Design screens read `required_on_design`. Completing a run reads
     * `required_on_execution`. Drafts never require an answer.
     *
     * Note `present` is not used: the payload only carries the fields a screen
     * rendered, and requiring every field to appear would make a partial save
     * from an API impossible.
     */
    private function requirednessOf(CustomField $field): string
    {
        if (! $this->enforceCustomFieldRequired) {
            return 'nullable';
        }

        $required = $this->customFieldsOnExecution
            ? $field->isRequiredOnExecution()
            : $field->isRequiredOnDesign();

        return $required ? 'required' : 'nullable';
    }

    /**
     * The answers to hand to `SaveCustomFieldValues`, once validated.
     *
     * Public because the controller reads it, like every other accessor a
     * request exposes; the rule building above stays internal.
     *
     * @return array<int|string, mixed>
     */
    public function customFieldAnswers(): array
    {
        /** @var array<int|string, mixed> $answers */
        $answers = $this->validated()['custom_fields'] ?? [];

        return $answers;
    }

    /**
     * Convenience for the common case: the rules for one subject's fields.
     *
     * @return array<string, mixed>
     */
    protected function customFieldRulesFor(CustomFieldSubject $subject): array
    {
        return $this->customFieldRules(app(ResolveCustomFields::class)->forSubject($subject));
    }

    /**
     * Drop the placeholder a cleared multi-value field is submitted with.
     *
     * A form cannot post an empty array: unchecking every box simply leaves the
     * key out, which here means "unchanged" rather than "cleared", so the
     * screens post one empty entry to say the field was on the form and nothing
     * was chosen. Removing it leaves `[]`, which reads as cleared — and still
     * fails `required`, which is what a mandatory field should do when a user
     * unticks the last box.
     *
     * Called from each request's `prepareForValidation()` rather than defined
     * as one here, because several of them already have that method from
     * another concern and two traits cannot both provide it.
     */
    protected function normaliseCustomFieldInput(): void
    {
        $answers = $this->input('custom_fields');

        if (! is_array($answers)) {
            return;
        }

        foreach ($answers as $fieldId => $answer) {
            if (! is_array($answer)) {
                continue;
            }

            $answers[$fieldId] = array_values(array_filter(
                $answer,
                static fn (mixed $chosen): bool => is_string($chosen) && trim($chosen) !== '',
            ));
        }

        $this->merge(['custom_fields' => $answers]);
    }
}
