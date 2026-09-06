<?php

namespace App\Actions\TestSpecification;

use App\Actions\Attachments\CopyAttachments;
use App\Actions\Audit\AuditLogger;
use App\Actions\CustomFields\CopyCustomFieldValues;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestCase;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Copies a suite, everything nested under it, and all of its test cases into
 * another parent, which may belong to another project.
 *
 * Unlike a move, this is allowed across projects: every copied case is
 * allocated a fresh external id from the target project.
 */
final class CopyTestSuite
{
    public function __construct(
        private CopyTestCase $copyTestCase,
        private readonly AuditLogger $audit,
        private readonly CopyAttachments $copyAttachments,
        private readonly CopyCustomFieldValues $copyCustomFieldValues,
    ) {}

    /**
     * Copy the suite under the given parent, or to a project's root when the
     * parent is null. The target project defaults to the parent's, then to the
     * source suite's.
     *
     * @throws AuthorizationException
     * @throws ValidationException when the target is inside the suite's own subtree or the copy would nest too deep
     */
    public function __invoke(
        User $user,
        TestSuite $suite,
        ?TestSuite $targetParent = null,
        ?TestProject $targetProject = null,
    ): TestSuite {
        $suite->loadMissing('testProject');
        $targetParent?->loadMissing('testProject');
        $targetProject ??= $targetParent !== null ? $targetParent->testProject : $suite->testProject;

        Gate::forUser($user)->authorize(Ability::ViewTestCases->value, $suite->testProject);
        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $targetProject);

        if ($targetParent !== null) {
            $this->assertTargetIsUsable($suite, $targetParent, $targetProject);
        }

        $rootDepth = $targetParent !== null ? $targetParent->depth() + 1 : 1;

        if ($rootDepth + $suite->descendantDepth() > TestSuite::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'parent_id' => 'That copy would nest test suites more than '.TestSuite::MAX_DEPTH.' levels deep.',
            ]);
        }

        return DB::transaction(function () use ($user, $suite, $targetParent, $targetProject): TestSuite {
            $copy = $this->duplicateSubtree($suite, $targetParent, $targetProject);

            /**
             * One record for the whole subtree, not one per copied node. A
             * subtree copy is a single act with a single intent, and recording
             * every descendant would bury the acts a reader is looking for.
             */
            $this->audit->record(AuditAction::TestSuiteCopied, $user, $copy, [
                'name' => $copy->name,
                'from_test_suite_id' => $suite->getKey(),
                'to_parent_id' => $targetParent?->getKey(),
                'to_test_project_id' => $targetProject->getKey(),
            ]);

            return $copy;
        });
    }

    /**
     * @throws ValidationException
     */
    private function assertTargetIsUsable(TestSuite $suite, TestSuite $targetParent, TestProject $targetProject): void
    {
        if ($targetParent->test_project_id !== $targetProject->getKey()) {
            throw ValidationException::withMessages([
                'parent_id' => 'The target test suite belongs to a different test project.',
            ]);
        }

        if ($targetParent->is($suite) || $suite->isAncestorOf($targetParent)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A test suite cannot be copied into itself or one of its own child suites.',
            ]);
        }
    }

    /**
     * The source subtree is read depth first in one recursive query, and its
     * cases with versions and steps in three more, so the copy never issues a
     * query per node. Depth first order guarantees a parent is copied before
     * its children need to reference it.
     */
    private function duplicateSubtree(TestSuite $suite, ?TestSuite $targetParent, TestProject $targetProject): TestSuite
    {
        $sourceSuites = $suite->subtree();

        $casesBySuite = TestCase::query()
            ->whereIn('test_suite_id', $sourceSuites->modelKeys())
            ->with('versions.steps')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('test_suite_id');

        /** @var array<int, TestSuite> $copiesBySourceId */
        $copiesBySourceId = [];

        foreach ($sourceSuites as $sourceSuite) {
            $isRoot = $sourceSuite->is($suite);

            $copy = new TestSuite;
            $copy->test_project_id = $targetProject->getKey();
            $copy->parent_id = $isRoot
                ? $targetParent?->getKey()
                : $copiesBySourceId[$sourceSuite->parent_id]->getKey();
            $copy->name = $sourceSuite->name;
            $copy->description = $sourceSuite->description;
            $copy->sort_order = $isRoot
                ? $this->nextSortOrder($targetProject, $targetParent)
                : $sourceSuite->sort_order;
            $copy->save();

            $copiesBySourceId[$sourceSuite->getKey()] = $copy;

            ($this->copyAttachments)($sourceSuite, $copy);

            /*
             * The suites' own answers. The cases' answers travel per version
             * inside `CopyTestCase::duplicate()`.
             */
            ($this->copyCustomFieldValues)($sourceSuite, $copy);

            foreach ($casesBySuite->get($sourceSuite->getKey(), collect()) as $case) {
                $this->copyTestCase->duplicate($case, $copy);
            }
        }

        return $copiesBySourceId[$suite->getKey()];
    }

    /**
     * Place the copied suite after its new last sibling.
     */
    private function nextSortOrder(TestProject $project, ?TestSuite $parent): int
    {
        return (int) TestSuite::query()
            ->where('test_project_id', $project->getKey())
            ->where('parent_id', $parent?->getKey())
            ->max('sort_order') + 1;
    }
}
