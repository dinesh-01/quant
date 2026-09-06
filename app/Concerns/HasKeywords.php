<?php

namespace App\Concerns;

use App\Models\Keyword;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The keywords relation, plus the two ways anything ever filters on it.
 *
 * Shared rather than written per model so that giving test suites or
 * requirements keywords later is a pivot table and one `use` line. The pivot
 * name is conventional (`keyword_test_case`), so nothing needs naming here.
 *
 * @phpstan-require-extends Model
 */
trait HasKeywords
{
    /**
     * @return BelongsToMany<Keyword, $this>
     */
    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class)->orderBy('name');
    }

    /**
     * Filter to rows carrying **any** of the given keywords.
     *
     * @param  Builder<$this>  $query
     * @param  list<int>  $keywordIds
     */
    #[Scope]
    protected function withAnyKeyword(Builder $query, array $keywordIds): void
    {
        if ($keywordIds === []) {
            return;
        }

        $query->whereHas('keywords', function (Builder $keywords) use ($keywordIds): void {
            $keywords->whereIn('keywords.id', $keywordIds);
        });
    }

    /**
     * Filter to rows carrying **every** one of the given keywords.
     *
     * Counted rather than joined once per keyword, so the query does not grow
     * with how many terms the user picked. The pivot's composite primary key
     * makes the count exact without a `DISTINCT`.
     *
     * @param  Builder<$this>  $query
     * @param  list<int>  $keywordIds
     */
    #[Scope]
    protected function withAllKeywords(Builder $query, array $keywordIds): void
    {
        if ($keywordIds === []) {
            return;
        }

        $query->whereHas(
            'keywords',
            fn (Builder $keywords) => $keywords->whereIn('keywords.id', $keywordIds),
            '=',
            count(array_unique($keywordIds)),
        );
    }
}
