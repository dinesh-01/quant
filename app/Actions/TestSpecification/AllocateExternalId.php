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
        return DB::transaction(function () use ($project): int {
            $locked = TestProject::query()
                ->whereKey($project->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->test_case_counter = $locked->test_case_counter + 1;
            $locked->save();

            return $locked->test_case_counter;
        });
    }
}
