<?php

namespace App\Actions\TestSpecification;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\TestCaseExecutionType;
use App\Enums\TestCaseImportance;
use App\Enums\TestCaseStatus;
use App\Models\TestCase;
use App\Models\TestCaseVersion;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Creates a test case in a suite, together with its first version.
 *
 * A case is never useful without a version, so both are written in one
 * transaction along with the external id allocation.
 */
final class CreateTestCase
{
    public function __construct(
        private AllocateExternalId $allocateExternalId,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, sort_order?: int, summary?: string|null, preconditions?: string|null, status?: TestCaseStatus, importance?: TestCaseImportance, execution_type?: TestCaseExecutionType, estimated_duration?: string|null}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestSuite $suite, array $attributes): TestCase
    {
        $suite->loadMissing('testProject');
        $project = $suite->testProject;

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $project);

        return DB::transaction(function () use ($user, $suite, $project, $attributes): TestCase {
            $case = new TestCase;
            $case->fill($attributes);
            $case->test_project_id = $project->getKey();
            $case->test_suite_id = $suite->getKey();
            $case->external_id = ($this->allocateExternalId)($project);
            $case->sort_order = $attributes['sort_order'] ?? $this->nextSortOrder($suite);
            $case->save();

            $version = new TestCaseVersion;
            $version->fill($attributes);
            $version->test_case_id = $case->getKey();
            $version->version = 1;
            $version->is_open = true;
            $version->author_id = $user->getKey();
            $version->save();

            $case->setRelation('latestVersion', $version);

            /**
             * One record, not two. The first version is part of creating a
             * case rather than an act of its own, and a log that says both
             * happened tells a reader nothing the first line did not.
             */
            $this->audit->record(AuditAction::TestCaseCreated, $user, $case, [
                'name' => $case->name,
                'external_id' => $case->external_id,
                'test_suite_id' => $case->test_suite_id,
            ]);

            return $case;
        });
    }

    /**
     * Place the case after the last one already in the suite.
     */
    private function nextSortOrder(TestSuite $suite): int
    {
        return (int) TestCase::query()
            ->where('test_suite_id', $suite->getKey())
            ->max('sort_order') + 1;
    }
}
