<?php

namespace App\Concerns;

use App\Models\Keyword;
use App\Models\TestCase;
use App\Models\TestProject;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;

/**
 * Turns keyword ids from a form into this project's keywords, or refuses.
 *
 * Shared by the two assignment actions because it is the check that keeps
 * project vocabularies apart, and a second copy of it is a second chance to get
 * it wrong. Ids arrive from a request as bare numbers: nothing about a `7` says
 * which project it belongs to, and legacy's `addKeywords` never asked — its
 * cross-project case copy put the source project's keyword ids on cases in the
 * target project for years, through a bug in the mapping it meant to apply.
 */
trait ResolvesProjectKeywords
{
    /**
     * @param  list<int>  $keywordIds
     * @return EloquentCollection<int, Keyword>
     *
     * @throws ValidationException when any id is not one of this project's
     */
    protected function resolveKeywords(TestProject $project, array $keywordIds): EloquentCollection
    {
        $keywordIds = array_values(array_unique($keywordIds));

        $keywords = Keyword::query()
            ->forProject($project)
            ->whereKey($keywordIds)
            ->get();

        if ($keywords->count() !== count($keywordIds)) {
            throw ValidationException::withMessages([
                'keywords' => 'One of those keywords does not belong to this test project.',
            ]);
        }

        return $keywords;
    }

    /**
     * The pivot's table name, read from the relation so this cannot drift from
     * the migration that named it.
     */
    protected function keywordPivotTable(): string
    {
        return (new TestCase)->keywords()->getTable();
    }
}
