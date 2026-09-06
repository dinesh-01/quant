<?php

namespace App\Actions\TestSpecification;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\TestCaseRelationType;
use App\Models\TestCase;
use App\Models\TestCaseRelation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Links two test cases in the same project.
 *
 * Related is symmetric: a reverse row of the same type is treated as the
 * same link and refused. Depends-on and blocks keep their direction.
 */
final class CreateTestCaseRelation
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(
        User $user,
        TestCase $source,
        TestCase $destination,
        TestCaseRelationType $type,
    ): TestCaseRelation {
        $source->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $source->testProject);

        if ($source->getKey() === $destination->getKey()) {
            throw ValidationException::withMessages([
                'destination' => 'A test case cannot be related to itself.',
            ]);
        }

        if ($source->test_project_id !== $destination->test_project_id) {
            throw ValidationException::withMessages([
                'destination' => 'Both test cases must belong to the same project.',
            ]);
        }

        $already = TestCaseRelation::query()
            ->where('type', $type->value)
            ->where(function ($query) use ($source, $destination, $type): void {
                $query->where(function ($pair) use ($source, $destination): void {
                    $pair->where('source_id', $source->getKey())
                        ->where('destination_id', $destination->getKey());
                });

                if ($type->isSymmetric()) {
                    $query->orWhere(function ($pair) use ($source, $destination): void {
                        $pair->where('source_id', $destination->getKey())
                            ->where('destination_id', $source->getKey());
                    });
                }
            })
            ->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'destination' => 'Those test cases are already linked that way.',
            ]);
        }

        $relation = new TestCaseRelation;
        $relation->source()->associate($source);
        $relation->destination()->associate($destination);
        $relation->type = $type;
        $relation->author_id = $user->getKey();
        $relation->save();

        $this->audit->record(AuditAction::TestCaseRelationCreated, $user, $source, [
            'source' => $source->name,
            'destination' => $destination->name,
            'destination_id' => $destination->getKey(),
            'type' => $type->value,
        ]);

        return $relation;
    }
}
