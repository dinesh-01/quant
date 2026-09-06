<?php

namespace App\Actions\Keywords;

use App\Actions\Audit\AuditLogger;
use App\Concerns\ResolvesProjectKeywords;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\KeywordBulkMode;
use App\Models\Keyword;
use App\Models\TestCase;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Adds or removes keywords across the cases under a suite in one go.
 *
 * This is legacy's keyword assignment screen, and it is the only practical way
 * to tag a specification that already exists — a team adopting `regression`
 * after the fact is not going to open four hundred cases.
 *
 * Three differences from legacy. It adds or removes rather than replacing, so a
 * run cannot discard tags the person running it never saw. It records one audit
 * act for the whole run rather than none. And it does not silently skip cases:
 * legacy passed over any case whose latest version had been executed unless the
 * user held a further right, so a run could report success having quietly
 * missed half the subtree.
 *
 * Written against the pivot in set operations rather than per case: this is the
 * one place where a keyword write is expected to cover hundreds of rows, and a
 * loop of `sync()` calls would be a query per case.
 */
final class ApplyKeywordsToTestSuite
{
    use ResolvesProjectKeywords;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int>  $keywordIds  ignored when the mode is `ClearAll`
     * @return int how many cases ended up different
     *
     * @throws AuthorizationException
     * @throws ValidationException when a keyword belongs to another project
     */
    public function __invoke(
        User $user,
        TestSuite $suite,
        KeywordBulkMode $mode,
        array $keywordIds = [],
        bool $directChildrenOnly = false,
    ): int {
        $suite->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::AssignKeywords->value, $suite->testProject);

        $clearing = $mode === KeywordBulkMode::ClearAll;

        /** @var EloquentCollection<int, Keyword> $keywords */
        $keywords = $clearing
            ? new EloquentCollection
            : $this->resolveKeywords($suite->testProject, $keywordIds);

        if (! $clearing && $keywords->isEmpty()) {
            return 0;
        }

        $caseIds = $this->caseIds($suite, $directChildrenOnly);

        if ($caseIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($user, $suite, $mode, $keywords, $caseIds): int {
            /** @var list<int> $targetIds */
            $targetIds = $keywords->modelKeys();

            $changed = match ($mode) {
                KeywordBulkMode::Assign => $this->assign($caseIds, $targetIds),
                KeywordBulkMode::Remove => $this->detach($caseIds, $targetIds),
                KeywordBulkMode::ClearAll => $this->detach($caseIds, null),
            };

            if ($changed === 0) {
                return 0;
            }

            /*
             * One record for the run, against the suite, the same judgement a
             * subtree copy is recorded under: one button press is one act, and
             * forty records would bury the acts a reader came for. The count
             * and the words answer "why does everything under here say smoke?".
             */
            $this->audit->record(AuditAction::TestSuiteKeywordsApplied, $user, $suite, [
                'name' => $suite->name,
                'mode' => $mode->value,
                'keywords' => $keywords->pluck('name')->values()->all(),
                'test_cases' => $changed,
            ]);

            return $changed;
        });
    }

    /**
     * Insert the pairs that are not already there.
     *
     * The existing pairs are read first so the return value can be the number
     * of *cases* that changed rather than the number of rows written, which is
     * what the screen reports and what the trail records.
     *
     * @param  list<int>  $caseIds
     * @param  list<int>  $keywordIds
     */
    private function assign(array $caseIds, array $keywordIds): int
    {
        $existing = DB::table($this->keywordPivotTable())
            ->whereIn('test_case_id', $caseIds)
            ->whereIn('keyword_id', $keywordIds)
            ->get(['test_case_id', 'keyword_id'])
            ->groupBy('test_case_id')
            ->map(fn ($rows) => $rows->pluck('keyword_id')->all());

        $rows = [];
        $touched = [];

        foreach ($caseIds as $caseId) {
            foreach ($keywordIds as $keywordId) {
                if (in_array($keywordId, $existing->get($caseId, []), false)) {
                    continue;
                }

                $rows[] = ['test_case_id' => $caseId, 'keyword_id' => $keywordId];
                $touched[$caseId] = true;
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($this->keywordPivotTable())->insert($chunk);
        }

        return count($touched);
    }

    /**
     * Delete the pairs for these cases, either for the given keywords or for
     * all of them.
     *
     * @param  list<int>  $caseIds
     * @param  list<int>|null  $keywordIds  null removes every keyword
     */
    private function detach(array $caseIds, ?array $keywordIds): int
    {
        $doomed = DB::table($this->keywordPivotTable())
            ->whereIn('test_case_id', $caseIds)
            ->when($keywordIds !== null, fn ($query) => $query->whereIn('keyword_id', $keywordIds))
            ->distinct()
            ->pluck('test_case_id');

        if ($doomed->isEmpty()) {
            return 0;
        }

        DB::table($this->keywordPivotTable())
            ->whereIn('test_case_id', $doomed->all())
            ->when($keywordIds !== null, fn ($query) => $query->whereIn('keyword_id', $keywordIds))
            ->delete();

        return $doomed->count();
    }

    /**
     * The cases the run covers: those directly in the suite, or those anywhere
     * beneath it.
     *
     * The subtree is one recursive query and the ids one more, so the cost does
     * not grow with how deep the tree is.
     *
     * @return list<int>
     */
    private function caseIds(TestSuite $suite, bool $directChildrenOnly): array
    {
        $suiteIds = $directChildrenOnly
            ? [$suite->getKey()]
            : $suite->subtree()->modelKeys();

        /** @var list<int> $ids */
        $ids = TestCase::query()
            ->whereIn('test_suite_id', $suiteIds)
            ->pluck('id')
            ->all();

        return $ids;
    }
}
