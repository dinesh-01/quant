<?php

namespace App\Concerns;

use App\Actions\CustomFields\ResolveCustomFields;
use App\Models\CustomField;
use App\Models\CustomFieldSubject;

/**
 * Handing a screen the fields it should show and the answers already given.
 *
 * The definition travels with the answer, because the input to render and the
 * rules to hint at are both properties of the field rather than of the page:
 * one screen renders whatever the project has enabled, and adding a field to a
 * project needs no change here.
 *
 * No authorization, for the same reason as `PresentsAttachments`: the answers
 * are part of the thing they hang off, which the caller has already authorized.
 */
trait PresentsCustomFields
{
    /**
     * @return list<array<string, mixed>>
     */
    protected function customFieldProps(CustomFieldSubject $subject, bool $onExecution = false): array
    {
        $fields = app(ResolveCustomFields::class)->forSubject($subject);

        if ($fields->isEmpty()) {
            return [];
        }

        $answers = app(ResolveCustomFields::class)->answersFor($subject);

        return array_values($fields
            ->map(fn (CustomField $field): array => [
                'id' => $field->id,
                'name' => $field->name,
                'label' => $field->label,
                'type' => $field->type->value,
                'options' => $field->options(),
                'is_required' => $onExecution
                    ? $field->isRequiredOnExecution()
                    : $field->isRequiredOnDesign(),
                'maximum_length' => $field->maximumLength(),
                /*
                 * The default only fills an unanswered field, and only on the
                 * screen: storing it on save would make "nobody has said" and
                 * "somebody agreed with the suggestion" the same row.
                 */
                'answer' => $field->type->decode(
                    $answers[$field->id] ?? $field->default_value,
                ),
            ])
            ->all());
    }
}
