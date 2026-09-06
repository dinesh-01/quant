<?php

namespace App\Actions\Keywords;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Keyword;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Renames a keyword or edits its notes.
 *
 * A rename carries every existing assignment with it, because the assignments
 * point at the row rather than the word. That is the point of a curated
 * vocabulary: correcting a spelling fixes it everywhere at once, where a free
 * text tag would have left the old spelling on every case that used it.
 */
final class UpdateKeyword
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, notes: string|null}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, Keyword $keyword, array $attributes): Keyword
    {
        $keyword->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageKeywords->value, $keyword->testProject);

        $keyword->fill($attributes);

        $properties = $this->audit->changes($keyword);

        $keyword->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::KeywordUpdated, $user, $keyword, $properties);
        }

        return $keyword;
    }
}
