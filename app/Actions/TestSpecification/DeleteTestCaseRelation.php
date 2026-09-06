<?php

namespace App\Actions\TestSpecification;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestCaseRelation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Removes a link between two test cases.
 */
final class DeleteTestCaseRelation
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestCaseRelation $relation): void
    {
        $relation->loadMissing(['source.testProject', 'destination']);

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $relation->source->testProject);

        $this->audit->record(AuditAction::TestCaseRelationDeleted, $user, $relation->source, [
            'source' => $relation->source->name,
            'destination' => $relation->destination->name,
            'destination_id' => $relation->destination_id,
            'type' => $relation->type->value,
        ]);

        $relation->delete();
    }
}
