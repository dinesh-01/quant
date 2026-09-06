<?php

namespace App\Actions\Builds;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Build;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits a build, including opening and closing it for execution.
 *
 * `is_open` is not `is_active`. Closing stops new results; the build stays
 * listed because its results are the record of a finished drop. Phase 6
 * enforces the execution side.
 */
final class UpdateBuild
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name?: string, notes?: string|null, is_active?: bool, is_open?: bool, release_date?: string|null}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException when the name is empty or already taken here
     */
    public function __invoke(User $user, Build $build, array $attributes): Build
    {
        $build->loadMissing('testPlan');

        Gate::forUser($user)->authorize(Ability::ManageBuilds->value, $build->testPlan);

        if (array_key_exists('name', $attributes)) {
            $attributes['name'] = trim($attributes['name']);

            if ($attributes['name'] === '') {
                throw ValidationException::withMessages([
                    'name' => 'A build needs a name.',
                ]);
            }

            $taken = Build::query()
                ->forPlan($build->testPlan)
                ->where('name', $attributes['name'])
                ->whereKeyNot($build->getKey())
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'name' => 'This plan already has a build with that name.',
                ]);
            }
        }

        $build->fill($attributes);

        $properties = $this->audit->changes($build);

        $build->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::BuildUpdated, $user, $build, $properties);
        }

        return $build;
    }
}
