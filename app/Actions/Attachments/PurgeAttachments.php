<?php

namespace App\Actions\Attachments;

use App\Models\Attachment;
use App\Models\Execution;
use App\Models\TestCase;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Support\Facades\Storage;

/**
 * Removes the attachments belonging to something about to be deleted.
 *
 * This exists because a polymorphic link cannot carry a foreign key, so no
 * `ON DELETE CASCADE` reaches these rows. Deleting a project cascades through
 * suites, cases, versions and steps in the database and leaves every attachment
 * row behind, pointing at ids that no longer exist — and every file on the disk
 * with nothing to say it is unreferenced. Legacy had exactly this gap and
 * leaked through it whenever a caller forgot its cleanup helper.
 *
 * **Every action that deletes a possible attachment parent must call this.**
 * That is `DeleteTestProject`, `DeleteTestSuite`, `DeleteTestCase`,
 * `DeleteTestCaseVersion`, `DeleteTestPlan` and `DeleteExecution`. There is
 * a test asserting no rows survive each one.
 *
 * Deliberately does not authorize and takes no acting user: it is an internal
 * collaborator of delete actions that have already authorized the whole
 * operation, following the same rule as `CopyTestCase::duplicate()`. Never call
 * it from a controller.
 */
final class PurgeAttachments
{
    /**
     * Everything in a project: the project's own files, every suite's, and
     * every version of every case.
     *
     * @return int how many attachments were removed, for the caller's audit record
     */
    public function forProject(TestProject $project): int
    {
        $suiteIds = TestSuite::query()
            ->where('test_project_id', $project->getKey())
            ->pluck('id')
            ->all();

        $versionIds = TestCaseVersion::query()
            ->whereIn(
                'test_case_id',
                TestCase::query()->where('test_project_id', $project->getKey())->select('id'),
            )
            ->pluck('id')
            ->all();

        $planIds = TestPlan::query()
            ->where('test_project_id', $project->getKey())
            ->pluck('id')
            ->all();

        $executionIds = $planIds === []
            ? []
            : Execution::query()->whereIn('test_plan_id', $planIds)->pluck('id')->all();

        return $this->remove([
            TestProject::class => [$project->getKey()],
            TestSuite::class => $suiteIds,
            TestCaseVersion::class => $versionIds,
            TestPlan::class => $planIds,
            Execution::class => $executionIds,
        ]);
    }

    /**
     * A suite, the suites beneath it, and the versions of every case in any of
     * them — which is the whole of what the database cascade will remove.
     */
    public function forSuiteSubtree(TestSuite $suite): int
    {
        $suiteIds = $suite->subtree()->modelKeys();

        $versionIds = TestCaseVersion::query()
            ->whereIn(
                'test_case_id',
                TestCase::query()->whereIn('test_suite_id', $suiteIds)->select('id'),
            )
            ->pluck('id')
            ->all();

        return $this->remove([
            TestSuite::class => $suiteIds,
            TestCaseVersion::class => $versionIds,
        ]);
    }

    /**
     * Every version of one case. The case itself holds no attachments —
     * they hang off versions, so a file stays with the revision it describes.
     */
    public function forTestCase(TestCase $case): int
    {
        return $this->remove([
            TestCaseVersion::class => $case->versions()->pluck('id')->all(),
        ]);
    }

    public function forVersion(TestCaseVersion $version): int
    {
        return $this->remove([
            TestCaseVersion::class => [$version->getKey()],
        ]);
    }

    public function forTestPlan(TestPlan $plan): int
    {
        return $this->remove([
            TestPlan::class => [$plan->getKey()],
            Execution::class => $plan->executions()->pluck('id')->all(),
        ]);
    }

    public function forExecution(Execution $execution): int
    {
        return $this->remove([
            Execution::class => [$execution->getKey()],
        ]);
    }

    /**
     * Delete the rows, then the files.
     *
     * That order on purpose: a row without a file is a download that fails
     * forever, a file without a row costs disk and nothing else. So the
     * database is made correct first and the disk is tidied after.
     *
     * @param  array<class-string, array<int, mixed>>  $idsByType
     */
    private function remove(array $idsByType): int
    {
        $idsByType = array_filter($idsByType, fn (array $ids): bool => $ids !== []);

        /*
         * Returning early is not an optimisation, it is the safety check. An
         * empty nested `where` adds no constraint at all, so carrying on here
         * with nothing to match would issue an unfiltered delete and take every
         * attachment in the installation. Deleting a suite that holds no files
         * reaches this line routinely.
         */
        if ($idsByType === []) {
            return 0;
        }

        $query = Attachment::query()->where(function ($outer) use ($idsByType): void {
            foreach ($idsByType as $type => $ids) {
                $outer->orWhere(function ($inner) use ($type, $ids): void {
                    $inner->where('attachable_type', $type)
                        ->whereIn('attachable_id', $ids);
                });
            }
        });

        /*
         * Read the paths out before deleting, since afterwards there is nothing
         * left to say where the files were. Only the paths are selected, so the
         * cost does not grow with how much metadata a row carries.
         */
        $doomed = $query->clone()->pluck('disk_path', 'id');

        if ($doomed->isEmpty()) {
            return 0;
        }

        $removed = $query->delete();

        Storage::disk((string) config('attachments.disk'))->delete($doomed->values()->all());

        return $removed;
    }
}
