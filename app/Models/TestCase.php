<?php

namespace App\Models;

use App\Concerns\HasKeywords;
use Database\Factories\TestCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A test case, which owns the versions that carry its actual content.
 *
 * `test_project_id` is denormalised from the owning suite so that the external
 * id stays unique per project and so authorization can resolve the scope
 * without walking the suite tree. Moving a case never changes it: cases move
 * within a project and are copied between projects.
 *
 * @property int $id
 * @property int $test_project_id
 * @property int $test_suite_id
 * @property int $external_id the N in the PREFIX-N identifier users quote
 * @property string $name
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestProject $testProject
 * @property-read TestSuite $testSuite
 * @property-read EloquentCollection<int, TestCaseVersion> $versions
 * @property-read TestCaseVersion|null $latestVersion
 * @property-read EloquentCollection<int, Keyword> $keywords
 */
#[Fillable(['name', 'sort_order'])]
class TestCase extends Model
{
    /** @use HasFactory<TestCaseFactory> */
    use HasFactory;

    /**
     * Keywords are assigned to the case, not to a version — see the pivot
     * migration for why legacy's version-level assignment is not reproduced.
     */
    use HasKeywords;

    /**
     * @return BelongsTo<TestProject, $this>
     */
    public function testProject(): BelongsTo
    {
        return $this->belongsTo(TestProject::class);
    }

    /**
     * @return BelongsTo<TestSuite, $this>
     */
    public function testSuite(): BelongsTo
    {
        return $this->belongsTo(TestSuite::class);
    }

    /**
     * @return HasMany<TestCaseVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TestCaseVersion::class)->orderBy('version');
    }

    /**
     * The highest numbered version, which is the one the authoring screens
     * work on and the one a new version is copied from.
     *
     * @return HasOne<TestCaseVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(TestCaseVersion::class)->ofMany('version', 'max');
    }

    /**
     * @return HasMany<TestCaseRelation, $this>
     */
    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(TestCaseRelation::class, 'source_id');
    }

    /**
     * @return HasMany<TestCaseRelation, $this>
     */
    public function incomingRelations(): HasMany
    {
        return $this->hasMany(TestCaseRelation::class, 'destination_id');
    }

    /**
     * Filter to cases matching a term typed by a user, against the case name
     * and, when the term is all digits, the N of its `PREFIX-N` identifier.
     *
     * The term is escaped before it reaches `LIKE`, because `%`, `_` and `\`
     * are literal characters to someone searching for `100%` or `user_id`.
     * Legacy interpolated the raw term, so a search for `%` returned every case
     * in the project and `_` matched any single character.
     *
     * Escaping assumes MySQL's default backslash escape character; it would
     * need an explicit `ESCAPE` clause under `NO_BACKSLASH_ESCAPES`.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);

        $query->where(function (Builder $query) use ($escaped, $term): void {
            $query->whereLike('name', "%{$escaped}%");

            if ($term !== '' && ctype_digit($term)) {
                $query->orWhere('external_id', (int) $term);
            }
        });
    }

    /**
     * The identifier users quote in bug reports and documents, for example
     * `QUANTA-42`.
     *
     * Eager load `testProject` before calling this across a collection.
     */
    public function fullExternalId(): string
    {
        return "{$this->testProject->prefix}-{$this->external_id}";
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'external_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
