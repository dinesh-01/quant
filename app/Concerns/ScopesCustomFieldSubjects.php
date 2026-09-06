<?php

namespace App\Concerns;

use App\Enums\CustomFieldEntity;
use App\Models\Execution;
use App\Models\TestCase;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finding everything in a project that one kind of custom field hangs off.
 *
 * Shared because two very different jobs need exactly the same question
 * answered — counting what a project has answered, and deleting it — and
 * because the test case branch is the easy one to get wrong: a field defined
 * against "test case" is answered per *version*, so the subjects are the
 * versions of the project's cases rather than the cases themselves.
 */
trait ScopesCustomFieldSubjects
{
    /**
     * A query selecting the ids, left as a query so it can be used as a
     * subquery rather than pulling every id of a large project into memory.
     *
     * @return Builder<TestSuite>|Builder<TestCaseVersion>|Builder<TestPlan>|Builder<Execution>
     */
    protected function projectSubjectQuery(TestProject $project, CustomFieldEntity $entity): Builder
    {
        return match ($entity) {
            CustomFieldEntity::TestSuite => TestSuite::query()
                ->select('id')
                ->where('test_project_id', $project->getKey()),
            CustomFieldEntity::TestCase => TestCaseVersion::query()
                ->select('id')
                ->whereIn(
                    'test_case_id',
                    TestCase::query()->select('id')->where('test_project_id', $project->getKey()),
                ),
            CustomFieldEntity::TestPlan => TestPlan::query()
                ->select('id')
                ->where('test_project_id', $project->getKey()),
            CustomFieldEntity::Execution => Execution::query()
                ->select('id')
                ->whereIn(
                    'test_plan_id',
                    TestPlan::query()->select('id')->where('test_project_id', $project->getKey()),
                ),
        };
    }
}
