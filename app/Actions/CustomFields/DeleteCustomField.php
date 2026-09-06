<?php

namespace App\Actions\CustomFields;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Removes a definition, and with it every answer anybody ever gave it.
 *
 * This is the widest-reaching act in the area, which is why the record of it
 * carries how many projects had the field enabled and how many answers went
 * with it: after the fact there is nothing left to count. Legacy deleted the
 * same way behind a single JavaScript confirm.
 *
 * The rows go by foreign key cascade — both the project assignments and the
 * values reference `custom_fields` — so the count is read before the delete and
 * the whole thing runs in one transaction.
 */
final class DeleteCustomField
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, CustomField $field): void
    {
        Gate::forUser($user)->authorize(Ability::ManageCustomFields->value);

        DB::transaction(function () use ($user, $field): void {
            /* Before the delete: afterwards there is nothing left to count. */
            $this->audit->record(AuditAction::CustomFieldDeleted, $user, $field, [
                'name' => $field->name,
                'label' => $field->label,
                'type' => $field->type->value,
                'entity_type' => $field->entity_type->value,
                'projects' => $field->testProjects()->count(),
                'answers' => $field->values()->count(),
            ]);

            $field->delete();
        });
    }
}
