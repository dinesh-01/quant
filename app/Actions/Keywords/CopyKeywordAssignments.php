<?php

namespace App\Actions\Keywords;

use App\Models\Keyword;
use App\Models\TestCase;

/**
 * Carries a case's keywords onto a copy of it.
 *
 * Within one project this is the same rows against a new case. Across projects
 * it cannot be: a keyword belongs to a project, so the target's own vocabulary
 * is the only thing the copy may point at. Keywords are therefore **matched by
 * name**, and anything the target project does not already have is dropped.
 *
 * Dropping is the deliberate half. Creating the missing keywords would be the
 * friendlier behaviour, but it would let anyone who may copy a case write into
 * a vocabulary that `manage_keywords` exists to control — and a project's
 * keyword list is a shared thing that becomes useless once anything can add to
 * it. The copy screen says what was dropped instead.
 *
 * Legacy meant to map by id and shipped a bug: `copyKeywordsTo()` reads an
 * undefined `$mappings` instead of the `$kwMappings` it was passed, so to this
 * day a cross-project case copy keeps the *source* project's keyword ids —
 * assignments pointing at keywords that belong to another project entirely.
 *
 * Deliberately does not authorize and takes no acting user: an internal
 * collaborator of copy actions that have already authorized both ends, the same
 * rule as `CopyTestCase::duplicate()`. Never call it from a controller.
 */
final class CopyKeywordAssignments
{
    /**
     * @return int how many keywords the copy ended up with
     */
    public function __invoke(TestCase $source, TestCase $copy): int
    {
        $names = $source->keywords()->pluck('name');

        if ($names->isEmpty()) {
            return 0;
        }

        if ($source->test_project_id === $copy->test_project_id) {
            $ids = $source->keywords()->pluck('keywords.id')->all();

            $copy->keywords()->sync($ids);

            return count($ids);
        }

        /*
         * Matched case-insensitively because the column's collation is, so
         * `Smoke` in one project answers to `smoke` in another — which is what
         * a person copying between projects means by it.
         */
        $matched = Keyword::query()
            ->where('test_project_id', $copy->test_project_id)
            ->whereIn('name', $names->all())
            ->pluck('id')
            ->all();

        $copy->keywords()->sync($matched);

        return count($matched);
    }
}
