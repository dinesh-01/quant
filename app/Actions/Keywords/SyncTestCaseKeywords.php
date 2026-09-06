<?php

namespace App\Actions\Keywords;

use App\Actions\Audit\AuditLogger;
use App\Concerns\ResolvesProjectKeywords;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestCase;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Sets which keywords a test case carries, replacing whatever it had.
 *
 * Replace rather than append, because the screen is a picker showing the whole
 * vocabulary with the current set selected — submitting it means "these are the
 * keywords now". Legacy's two entry points disagreed about this: its case
 * screen replaced and its bulk screen appended, so the same list of ids meant
 * different things depending on which form it arrived from.
 *
 * `assign_keywords` is separate from `manage_keywords` on purpose, and legacy
 * had the same split: a tester tagging cases does not thereby get to invent
 * vocabulary, and a lead curating the list does not need to touch cases.
 */
final class SyncTestCaseKeywords
{
    use ResolvesProjectKeywords;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int>  $keywordIds
     *
     * @throws AuthorizationException
     * @throws ValidationException when a keyword belongs to another project
     */
    public function __invoke(User $user, TestCase $case, array $keywordIds): void
    {
        $case->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::AssignKeywords->value, $case->testProject);

        $keywords = $this->resolveKeywords($case->testProject, $keywordIds);

        DB::transaction(function () use ($user, $case, $keywords): void {
            $before = $case->keywords()->pluck('name', 'keywords.id');

            $changes = $case->keywords()->sync($keywords->modelKeys());

            /*
             * Nothing changed means nothing to record. Re-submitting the same
             * picker is the commonest thing a screen like this does, and a
             * trail full of "changed the keywords" with no change in it is
             * worse than no entry at all.
             */
            if ($changes['attached'] === [] && $changes['detached'] === []) {
                return;
            }

            $this->audit->record(AuditAction::TestCaseKeywordsChanged, $user, $case, [
                'name' => $case->name,
                'added' => $keywords
                    ->whereIn('id', $changes['attached'])
                    ->pluck('name')
                    ->values()
                    ->all(),
                'removed' => $before
                    ->only($changes['detached'])
                    ->values()
                    ->all(),
            ]);
        });
    }
}
