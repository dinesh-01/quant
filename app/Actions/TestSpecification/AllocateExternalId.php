<?php

namespace App\Actions\TestSpecification;

use App\Models\TestProject;
use Illuminate\Support\Facades\DB;

/**
 * Hands out the next `PREFIX-N` number for a test project.
 *
 * The counter is read and written under a row lock inside a transaction, so two
 * concurrent creations cannot be given the same number. Legacy did an
 * `UPDATE ... SET tc_counter = tc_counter + 1` followed by a separate `SELECT`
 * and wrapped the pair in a filesystem `flock`, which is the race this replaces.
 *
 * This action deliberately performs no ability check. It is an internal
 * collaborator, never reached directly by a request; the action that creates or
 * copies the test case authorizes the operation.
 */
final class AllocateExternalId
{
    /**
     * Reserve and return the next external id for the project.
     *
     * The passed model is not refreshed, so re-read it if you need the new
     * counter value.
     */
    public function __invoke(TestProject $project): int
    {
        return $this->next($project, 'test_case_counter');
    }

    /**
     * Reserve and return the next plan number for the project, as in `CO-P12`.
     *
     * Plans are numbered from their own counter rather than their primary key,
     * so a project's plan numbers do not skip whenever another project creates
     * a plan.
     */
    public function forPlan(TestProject $project): int
    {
        return $this->next($project, 'test_plan_counter');
    }

    private function next(TestProject $project, string $column): int
    {
        return DB::transaction(function () use ($project, $column): int {
            $locked = TestProject::query()
                ->whereKey($project->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->{$column} = $locked->{$column} + 1;
            $locked->save();

            return $locked->{$column};
        });
    }
}
