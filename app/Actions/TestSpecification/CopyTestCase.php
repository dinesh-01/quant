<?php

namespace App\Actions\TestSpecification;

use App\Actions\Attachments\CopyAttachments;
use App\Actions\Audit\AuditLogger;
use App\Actions\CustomFields\CopyCustomFieldValues;
use App\Actions\Keywords\CopyKeywordAssignments;
use App\Actions\Platforms\CopyPlatformAssignments;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestCase;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Copies a test case into a suite, which may belong to another project.
 *
 * Every version is copied with its steps, its attachments, its custom field
 * answers, its design-time platforms and its automation-script links, the
 * case's keywords come along, and the copy is allocated a fresh external id
 * from the target project. This matches legacy, whose `copy_to` defaults are
 * `copyOnlyLatest => false`, `preserve_external_id => false` and
 * `keyword_assignments => true`. Platforms are matched by name across
 * projects and dropped when the target has no counterpart — see
 * `CopyPlatformAssignments`. Script links keep their repository path.
 *
 * Freeze state is preserved: copying a frozen version gives a frozen copy, so a
 * copied case reads exactly like its original. Only `create_new_version`
 * reopens a version.
 */
final class CopyTestCase
{
    public function __construct(
        private AllocateExternalId $allocateExternalId,
        private readonly AuditLogger $audit,
        private readonly CopyAttachments $copyAttachments,
        private readonly CopyCustomFieldValues $copyCustomFieldValues,
        private readonly CopyKeywordAssignments $copyKeywordAssignments,
        private readonly CopyPlatformAssignments $copyPlatformAssignments,
        private readonly CopyScriptLinks $copyScriptLinks,
    ) {}

    /**
     * Reading the source needs `view_test_cases` on its project and writing the
     * copy needs `manage_test_cases` on the target, which differ when the two
     * projects differ.
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestCase $case, TestSuite $targetSuite): TestCase
    {
        $case->loadMissing('testProject');
        $targetSuite->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ViewTestCases->value, $case->testProject);
        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $targetSuite->testProject);

        $copy = $this->duplicate($case, $targetSuite);

        /**
         * Recorded here and not in `duplicate()`, which CopyTestSuite calls
         * without an actor while copying a subtree — that copy records itself
         * once for the whole subtree instead.
         */
        $this->audit->record(AuditAction::TestCaseCopied, $user, $copy, [
            'name' => $copy->name,
            'external_id' => $copy->external_id,
            'from_test_case_id' => $case->getKey(),
            'to_test_suite_id' => $targetSuite->getKey(),
        ]);

        return $copy;
    }

    /**
     * Copy the case without authorizing.
     *
     * Only for callers that have already authorized the whole operation, such
     * as CopyTestSuite copying a subtree. Never call this from a controller.
     */
    public function duplicate(TestCase $case, TestSuite $targetSuite): TestCase
    {
        $targetSuite->loadMissing('testProject');
        $targetProject = $targetSuite->testProject;

        return DB::transaction(function () use ($case, $targetSuite, $targetProject): TestCase {
            $copy = new TestCase;
            $copy->test_project_id = $targetProject->getKey();
            $copy->test_suite_id = $targetSuite->getKey();
            $copy->external_id = ($this->allocateExternalId)($targetProject);
            $copy->name = $case->name;
            $copy->sort_order = (int) TestCase::query()
                ->where('test_suite_id', $targetSuite->getKey())
                ->max('sort_order') + 1;
            $copy->save();

            foreach ($case->versions as $version) {
                $this->copyVersion($version, $copy);
            }

            /*
             * Keywords are on the case rather than its versions, so they are
             * copied once here. Across projects they are matched by name and
             * anything unmatched is dropped — see `CopyKeywordAssignments`.
             */
            ($this->copyKeywordAssignments)($case, $copy);

            return $copy;
        });
    }

    private function copyVersion(TestCaseVersion $source, TestCase $target): void
    {
        $version = new TestCaseVersion;
        $version->test_case_id = $target->getKey();
        $version->version = $source->version;
        $version->summary = $source->summary;
        $version->preconditions = $source->preconditions;
        $version->status = $source->status;
        $version->importance = $source->importance;
        $version->execution_type = $source->execution_type;
        $version->estimated_duration = $source->estimated_duration;
        $version->is_open = $source->is_open;
        $version->author_id = $source->author_id;
        $version->updater_id = $source->updater_id;
        $version->save();

        foreach ($source->steps as $step) {
            $copy = new TestCaseStep;
            $copy->test_case_version_id = $version->getKey();
            $copy->sort_order = $step->sort_order;
            $copy->actions = $step->actions;
            $copy->expected_results = $step->expected_results;
            $copy->execution_type = $step->execution_type;
            $copy->save();
        }

        ($this->copyAttachments)($source, $version);

        /*
         * Per version, because that is what the answers hang off. Copying into
         * another project keeps only the fields that project has enabled, which
         * `CopyCustomFieldValues` decides by resolving against the target.
         */
        ($this->copyCustomFieldValues)($source, $version);

        /*
         * Per version, because platforms hang off the revision. Across
         * projects they are matched by name and unmatched ones are dropped.
         */
        ($this->copyPlatformAssignments)($source, $version);
        ($this->copyScriptLinks)($source, $version);
    }
}
