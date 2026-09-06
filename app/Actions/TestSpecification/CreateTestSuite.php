<?php

namespace App\Actions\TestSpecification;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Adds a suite to a project, either at the root or inside another suite.
 */
final class CreateTestSuite
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, description?: string|null, sort_order?: int}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException when the parent belongs to another project or the nesting limit is reached
     */
    public function __invoke(User $user, TestProject $project, ?TestSuite $parent, array $attributes): TestSuite
    {
        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $project);

        if ($parent !== null) {
            if ($parent->test_project_id !== $project->getKey()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'The parent test suite belongs to a different test project.',
                ]);
            }

            if ($parent->depth() >= TestSuite::MAX_DEPTH) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Test suites cannot be nested more than '.TestSuite::MAX_DEPTH.' levels deep.',
                ]);
            }
        }

        $suite = new TestSuite;
        $suite->fill($attributes);
        $suite->test_project_id = $project->getKey();
        $suite->parent_id = $parent?->getKey();
        $suite->sort_order = $attributes['sort_order'] ?? $this->nextSortOrder($project, $parent);

        $properties = $this->audit->changes($suite);

        $suite->save();

        $this->audit->record(AuditAction::TestSuiteCreated, $user, $suite, $properties);

        return $suite;
    }

    /**
     * Place the suite after its last sibling.
     */
    private function nextSortOrder(TestProject $project, ?TestSuite $parent): int
    {
        return (int) TestSuite::query()
            ->where('test_project_id', $project->getKey())
            ->where('parent_id', $parent?->getKey())
            ->max('sort_order') + 1;
    }
}
