<?php

namespace App\Actions\CustomFields;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Adds a definition to the application-wide catalogue.
 *
 * `manage_custom_fields` is a system ability, so this needs a global role: the
 * definition created here can be enabled by any project, and changing it later
 * reaches all of them.
 */
final class CreateCustomField
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, array $attributes): CustomField
    {
        Gate::forUser($user)->authorize(Ability::ManageCustomFields->value);

        $field = new CustomField;
        $field->fill($attributes);

        $properties = $this->audit->changes($field);

        $field->save();

        $this->audit->record(AuditAction::CustomFieldCreated, $user, $field, $properties);

        return $field;
    }
}
