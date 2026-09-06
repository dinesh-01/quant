<?php

namespace App\Actions\Keywords;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Keyword;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Removes a keyword from a project's vocabulary, and from every case carrying
 * it.
 *
 * **A keyword in use can be deleted.** The pivot's foreign key cascades, so the
 * assignments go with it. Refusing while it is assigned would be the more
 * cautious rule, but it makes retiring a keyword a manual sweep of every case
 * that has it — and the thing being destroyed is a label, not content. The
 * count of assignments is recorded so the trail says how far it reached.
 *
 * Legacy could refuse, but on the wrong grounds: two config switches blocked
 * deletion when a linked version had been executed or frozen, which protects
 * the *version's* history by making the project's vocabulary un-curatable.
 */
final class DeleteKeyword
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, Keyword $keyword): void
    {
        $keyword->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageKeywords->value, $keyword->testProject);

        DB::transaction(function () use ($user, $keyword): void {
            /* Before the delete: afterwards there is no name to report. */
            $this->audit->record(AuditAction::KeywordDeleted, $user, $keyword, [
                'name' => $keyword->name,
                'test_cases' => $keyword->testCases()->count(),
            ]);

            $keyword->delete();
        });
    }
}
