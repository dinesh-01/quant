<?php

namespace App\Actions\TestSpecification;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * Renames a suite or rewrites its description.
 *
 * Re-parenting is MoveTestSuite's job, so `parent_id` is not accepted here.
 */
final class UpdateTestSuite
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name?: string, description?: string|null}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestSuite $suite, array $attributes): TestSuite
    {
        $suite->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $suite->testProject);

        $suite->fill(Arr::only($attributes, ['name', 'description']));

        $properties = $this->audit->changes($suite);

        $suite->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::TestSuiteUpdated, $user, $suite, $properties);
        }

        return $suite;
    }
}
