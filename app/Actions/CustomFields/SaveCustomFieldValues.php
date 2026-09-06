<?php

namespace App\Actions\CustomFields;

use App\Models\CustomField;
use App\Models\CustomFieldSubject;
use App\Models\CustomFieldValue;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Records the answers to a subject's custom fields.
 *
 * The ability comes from the kind of field rather than from this action, so
 * whoever may edit the case may fill in the case's fields, and no separate
 * right has to be granted for data that is part of the thing itself.
 *
 * Only fields named in the payload are touched. A form renders every field it
 * shows and submits them all, so clearing one arrives as an empty value; an
 * API caller sending one field changes one field. Legacy could not express the
 * difference — it seeded every linked field with an empty string before
 * parsing the request, so any write path that did not send them all wiped the
 * rest, which is exactly the bug its own XML-RPC update method had.
 */
final class SaveCustomFieldValues
{
    public function __construct(private readonly ResolveCustomFields $fields) {}

    /**
     * @param  array<int|string, mixed>  $answers  keyed by custom field id
     * @return int the number of answers written or cleared
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, CustomFieldSubject $subject, array $answers): int
    {
        Gate::forUser($user)->authorize(
            $subject->customFieldEntity()->writeAbility()->value,
            $subject->customFieldGateScope(),
        );

        if ($answers === []) {
            return 0;
        }

        /*
         * Resolved rather than trusted. An id that is not enabled in this
         * project, or belongs to a field defined against another kind of thing,
         * is dropped: the form request rejects those with a message, and this
         * is the backstop for every other caller.
         */
        $applicable = $this->fields->forSubject($subject)
            ->keyBy(fn (CustomField $field): int => $field->id);

        $written = 0;

        DB::transaction(function () use ($subject, $answers, $applicable, &$written): void {
            foreach ($answers as $fieldId => $answer) {
                $field = $applicable->get((int) $fieldId);

                if (! $field instanceof CustomField) {
                    continue;
                }

                $written += $this->store($subject, $field, $answer);
            }
        });

        return $written;
    }

    /**
     * Write one answer, or remove it when the answer is blank.
     *
     * Clearing deletes the row rather than storing an empty one, which keeps
     * the table to answers somebody actually gave.
     */
    private function store(CustomFieldSubject $subject, CustomField $field, mixed $answer): int
    {
        $encoded = $field->type->encode($answer);

        if ($encoded === null) {
            return (int) $subject->customFieldValues()
                ->where('custom_field_id', $field->id)
                ->delete();
        }

        /*
         * Assigned rather than filled: the model guards everything, because the
         * only thing that should ever decide which field a row answers is this
         * action, working from what the project has enabled — never a key in a
         * request body.
         */
        $value = $subject->customFieldValues()
            ->where('custom_field_id', $field->getKey())
            ->first();

        if (! $value instanceof CustomFieldValue) {
            $value = new CustomFieldValue;
            $value->custom_field_id = $field->getKey();
        }

        $value->value = $encoded;

        if (! $value->isDirty()) {
            return 0;
        }

        /* `save()` on the relation is what sets the subject columns. */
        $subject->customFieldValues()->save($value);

        return 1;
    }
}
