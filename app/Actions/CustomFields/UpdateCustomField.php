<?php

namespace App\Actions\CustomFields;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits a definition, everywhere it is used at once.
 *
 * A label, an option list or a validation rule may be changed freely. What a
 * field *is* may not, once anybody has answered it: an existing answer was
 * written and checked under the old type, and there is no honest way to reread
 * a free text answer as a date or as one of five options. Legacy froze both the
 * type and the node type on first use for the same reason.
 *
 * Narrowing the options is deliberately allowed even where stored answers fall
 * outside the new list. The alternative — refusing the edit — would leave a
 * team unable to retire an option they no longer offer, and the answers already
 * given remain what was true when they were given.
 */
final class UpdateCustomField
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, CustomField $field, array $attributes): CustomField
    {
        Gate::forUser($user)->authorize(Ability::ManageCustomFields->value);

        $this->guardFrozenShape($field, $attributes);

        $field->fill($attributes);

        $properties = $this->audit->changes($field);

        $field->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::CustomFieldUpdated, $user, $field, $properties);
        }

        return $field;
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    private function guardFrozenShape(CustomField $field, array $attributes): void
    {
        $type = $attributes['type'] ?? null;
        $entity = $attributes['entity_type'] ?? null;

        $changingType = $type !== null && $this->valueOf($type) !== $field->type->value;
        $changingEntity = $entity !== null && $this->valueOf($entity) !== $field->entity_type->value;

        if (! $changingType && ! $changingEntity) {
            return;
        }

        /*
         * One query, and only when something is actually being changed, so the
         * ordinary case of renaming a label does not pay for this check.
         */
        if (! $field->isAnswered()) {
            return;
        }

        throw ValidationException::withMessages([
            $changingType ? 'type' : 'entity_type' => __('This cannot be changed now that :name has been filled in. Answers already given were validated against how it is defined today.', ['name' => $field->label]),
        ]);
    }

    private function valueOf(mixed $candidate): ?string
    {
        return match (true) {
            $candidate instanceof CustomFieldType, $candidate instanceof CustomFieldEntity => $candidate->value,
            is_string($candidate) => $candidate,
            default => null,
        };
    }
}
