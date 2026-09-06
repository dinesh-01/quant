<?php

namespace App\Actions\Platforms;

use App\Models\Platform;
use App\Models\TestCaseVersion;

/**
 * Carries a version's design-time platforms onto a copy of it.
 *
 * Within one project this is the same rows against a new version. Across
 * projects a platform id means nothing, so the target's own vocabulary is
 * matched by name and anything it does not have is dropped — the same rule
 * as `CopyKeywordAssignments`, and for the same reason: inventing platforms
 * in the target would let a copy write a vocabulary `manage_platforms`
 * exists to control.
 *
 * Deliberately does not authorize and takes no acting user. Never call it
 * from a controller.
 */
final class CopyPlatformAssignments
{
    /**
     * @return int how many platforms the copy ended up with
     */
    public function __invoke(TestCaseVersion $source, TestCaseVersion $copy): int
    {
        $source->loadMissing('testCase');
        $copy->loadMissing('testCase');

        $names = $source->platforms()->pluck('name');

        if ($names->isEmpty()) {
            return 0;
        }

        if ($source->testCase->test_project_id === $copy->testCase->test_project_id) {
            $ids = $source->platforms()->pluck('platforms.id')->all();

            $copy->platforms()->sync($ids);

            return count($ids);
        }

        $matched = Platform::query()
            ->where('test_project_id', $copy->testCase->test_project_id)
            ->whereIn('name', $names->all())
            ->pluck('id')
            ->all();

        $copy->platforms()->sync($matched);

        return count($matched);
    }
}
