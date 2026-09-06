<?php

namespace App\Actions\TestSpecification;

use App\Actions\Attachments\CopyAttachments;
use App\Actions\Audit\AuditLogger;
use App\Actions\CustomFields\CopyCustomFieldValues;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestCase;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Opens a new version of a test case, copied from an existing one.
 *
 * What a new version **copies** from its source: summary, preconditions,
 * status, importance, execution type, estimated duration, every step with its
 * actions, expected results, execution type and position, every attachment
 * — file and all, since two rows sharing one file would make deleting either
 * break the other — every custom field answer, every design-time platform
 * assignment, and every automation-script link. Platforms and scripts hang
 * off the version (unlike keywords), so the previous version keeps what it
 * said and the new one has to receive its own rows.
 *
 * What it **resets**: the version number becomes the case's highest plus one,
 * `is_open` becomes true even when the source is frozen, the author becomes the
 * acting user, and the updater is cleared.
 *
 * What it **leaves alone**: the case's `external_id`, which identifies the case
 * rather than the revision, and its keywords — they hang off the case, so a new
 * version carries them without anything being copied. Legacy copied keywords
 * per version and then read them off the newest one anyway.
 *
 * Deliberately not copied: requirement coverage links. Those hang off the
 * version so a new revision does not silently inherit or steal them — the
 * freeze-on-new-version rule. Cross-project copies of a case still have to
 * match platforms by name; that belongs with the copy actions, not with
 * branching a version inside one project.
 */
final class CreateTestCaseVersion
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CopyAttachments $copyAttachments,
        private readonly CopyCustomFieldValues $copyCustomFieldValues,
        private readonly CopyScriptLinks $copyScriptLinks,
    ) {}

    /**
     * Branch a new version from the given source, or from the latest version
     * when no source is named.
     *
     * @throws AuthorizationException
     * @throws ValidationException when the case has no version to copy or the source belongs elsewhere
     */
    public function __invoke(User $user, TestCase $case, ?TestCaseVersion $source = null): TestCaseVersion
    {
        $case->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $case->testProject);

        $source ??= $case->latestVersion;

        if ($source === null) {
            throw ValidationException::withMessages([
                'source_version_id' => 'This test case has no version to copy.',
            ]);
        }

        if ($source->test_case_id !== $case->getKey()) {
            throw ValidationException::withMessages([
                'source_version_id' => 'That version belongs to a different test case.',
            ]);
        }

        return DB::transaction(function () use ($user, $case, $source): TestCaseVersion {
            $version = new TestCaseVersion;
            $version->test_case_id = $case->getKey();
            $version->version = (int) $case->versions()->max('version') + 1;
            $version->summary = $source->summary;
            $version->preconditions = $source->preconditions;
            $version->status = $source->status;
            $version->importance = $source->importance;
            $version->execution_type = $source->execution_type;
            $version->estimated_duration = $source->estimated_duration;
            $version->is_open = true;
            $version->author_id = $user->getKey();
            $version->updater_id = null;
            $version->save();

            $this->copySteps($source, $version);
            $version->platforms()->sync($source->platforms()->pluck('id'));
            ($this->copyScriptLinks)($source, $version);

            $attachments = ($this->copyAttachments)($source, $version);
            $customFieldValues = ($this->copyCustomFieldValues)($source, $version);

            $this->audit->record(AuditAction::TestCaseVersionCreated, $user, $case, [
                'name' => $case->name,
                'version' => $version->version,
                'copied_from_version' => $source->version,
                'attachments' => $attachments,
                'custom_field_values' => $customFieldValues,
            ]);

            return $version;
        });
    }

    private function copySteps(TestCaseVersion $source, TestCaseVersion $target): void
    {
        foreach ($source->steps as $step) {
            $copy = new TestCaseStep;
            $copy->test_case_version_id = $target->getKey();
            $copy->sort_order = $step->sort_order;
            $copy->actions = $step->actions;
            $copy->expected_results = $step->expected_results;
            $copy->execution_type = $step->execution_type;
            $copy->save();
        }
    }
}
