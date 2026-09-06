<?php

namespace App\Actions\CustomFields;

use App\Concerns\ScopesCustomFieldSubjects;
use App\Enums\CustomFieldEntity;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Execution;
use App\Models\TestCase;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\TestSuite;

/**
 * Removes the custom field answers belonging to something about to be deleted.
 *
 * The same gap `PurgeAttachments` closes, for the same reason: the link to the
 * subject is polymorphic, so no foreign key and therefore no cascade reaches
 * these rows. Legacy leaked here too, and unevenly — its Postgres schema
 * declared `ON DELETE CASCADE` on the value tables while its MySQL schema
 * declared no foreign keys at all, so whether a delete leaked depended on which
 * database an installation ran.
 *
 * **Every action that deletes a possible subject must call this.** That is
 * `DeleteTestProject`, `DeleteTestSuite`, `DeleteTestCase`,
 * `DeleteTestCaseVersion`, `DeleteTestPlan` and `DeleteExecution`.
 *
 * Deliberately does not authorize and takes no acting user: an internal
 * collaborator of delete actions that have already authorized the operation,
 * following the same rule as `PurgeAttachments`. Never call it from a
 * controller.
 */
final class PurgeCustomFieldValues
{
    use ScopesCustomFieldSubjects;

    /**
     * Everything in a project: every suite, every version of every case, and
     * every plan — which is the whole of what the database cascade removes.
     *
     * @return int how many answers were removed, for the caller's audit record
     */
    public function forProject(TestProject $project): int
    {
        return $this->remove([
            TestSuite::class => $this->subjectIds($project, CustomFieldEntity::TestSuite),
            TestCaseVersion::class => $this->subjectIds($project, CustomFieldEntity::TestCase),
            TestPlan::class => $this->subjectIds($project, CustomFieldEntity::TestPlan),
            Execution::class => $this->subjectIds($project, CustomFieldEntity::Execution),
        ]);
    }

    /**
     * A suite, the suites beneath it, and the versions of every case in any of
     * them.
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
     * Every version of one case. The case itself holds no answers — a field
     * defined against "test case" is answered per version, so the data stays
     * with the revision it was written for.
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
     * One project's answers to one field, for when the field stops applying
     * there.
     *
     * Removing a field from a project deletes what that project answered,
     * rather than leaving rows nothing will ever read again. Legacy kept them —
     * its own comment says values are "not removed" on unlink — so re-enabling
     * a field resurrected answers written against a definition that may since
     * have changed type. Switching a field to inactive is the way to keep the
     * answers, and that is what the assignment screen offers.
     */
    public function forProjectField(TestProject $project, CustomField $field): int
    {
        return CustomFieldValue::query()
            ->where('custom_field_id', $field->getKey())
            ->where('subject_type', $field->entity_type->modelClass())
            ->whereIn('subject_id', $this->projectSubjectQuery($project, $field->entity_type))
            ->delete();
    }

    /**
     * The ids in one project of everything one kind of field hangs off.
     *
     * Materialised here, unlike `forProjectField` above, because `remove()`
     * combines several types into one `where` and has to know which of them
     * have anything to match.
     *
     * @return array<int, mixed>
     */
    private function subjectIds(TestProject $project, CustomFieldEntity $entity): array
    {
        return $this->projectSubjectQuery($project, $entity)->pluck('id')->all();
    }

    /**
     * @param  array<class-string, array<int, mixed>>  $idsByType
     */
    private function remove(array $idsByType): int
    {
        $idsByType = array_filter($idsByType, fn (array $ids): bool => $ids !== []);

        /*
         * The safety check, not an optimisation: an empty nested `where` adds
         * no constraint, so carrying on with nothing to match would issue an
         * unfiltered delete and take every answer in the installation. Deleting
         * a suite whose cases answered nothing reaches this line routinely.
         */
        if ($idsByType === []) {
            return 0;
        }

        return CustomFieldValue::query()
            ->where(function ($outer) use ($idsByType): void {
                foreach ($idsByType as $type => $ids) {
                    $outer->orWhere(function ($inner) use ($type, $ids): void {
                        $inner->where('subject_type', $type)
                            ->whereIn('subject_id', $ids);
                    });
                }
            })
            ->delete();
    }
}
