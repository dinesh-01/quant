<?php

namespace App\Actions\Keywords;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Keyword;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Adds a keyword to a project's vocabulary.
 *
 * `manage_keywords` is project-scoped, so a team can curate its own list
 * without any standing in another project.
 */
final class CreateKeyword
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, notes: string|null}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestProject $project, array $attributes): Keyword
    {
        Gate::forUser($user)->authorize(Ability::ManageKeywords->value, $project);

        $keyword = new Keyword;
        $keyword->fill($attributes);
        $keyword->testProject()->associate($project);

        $properties = $this->audit->changes($keyword);

        $keyword->save();

        $this->audit->record(AuditAction::KeywordCreated, $user, $keyword, $properties);

        return $keyword;
    }
}
