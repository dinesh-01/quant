<?php

namespace App\Models;

use Database\Factories\KeywordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A tag in one test project's vocabulary — `smoke`, `regression`, `flaky`.
 *
 * Keywords carry no behaviour of their own: they exist to be assigned to test
 * cases and then filtered on. What makes them worth a table rather than a free
 * text column is that the set is curated, so a typo cannot quietly create a
 * third spelling of `regression` that no filter will ever match.
 *
 * @property int $id
 * @property int $test_project_id
 * @property string $name
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestProject $testProject
 * @property-read EloquentCollection<int, TestCase> $testCases
 * @property-read int|null $test_cases_count
 */
#[Fillable(['name', 'notes'])]
class Keyword extends Model
{
    /** @use HasFactory<KeywordFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TestProject, $this>
     */
    public function testProject(): BelongsTo
    {
        return $this->belongsTo(TestProject::class);
    }

    /**
     * @return BelongsToMany<TestCase, $this>
     */
    public function testCases(): BelongsToMany
    {
        return $this->belongsToMany(TestCase::class);
    }

    /**
     * Order a catalogue the way a reader scans it.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function alphabetically(Builder $query): void
    {
        $query->orderBy('name');
    }

    /**
     * Filter to the keywords of one project.
     *
     * Every read of this table has to be scoped to a project, because a name is
     * only unique within one and two projects mean different things by the same
     * word.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function forProject(Builder $query, TestProject $project): void
    {
        $query->where('test_project_id', $project->getKey());
    }
}
