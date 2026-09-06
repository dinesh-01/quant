<?php

namespace App\Actions\TestSpecification;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestCase;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Renames a test case.
 *
 * The name lives on the case rather than the version, so renaming is not
 * blocked by a frozen version: freezing protects what was executed, and the
 * name is not part of that. Moving is MoveTestCase's job.
 */
final class UpdateTestCase
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name?: string}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestCase $case, array $attributes): TestCase
    {
        $case->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $case->testProject);

        $case->fill($attributes);

        $properties = $this->audit->changes($case);

        $case->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::TestCaseRenamed, $user, $case, $properties);
        }

        return $case;
    }
}
