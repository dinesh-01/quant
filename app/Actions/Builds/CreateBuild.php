<?php

namespace App\Actions\Builds;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Build;
use App\Models\TestPlan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Adds a build to a plan.
 *
 * Scoped to the plan: a plan role granting `manage_builds` can create one
 * here, and a plan role withholding it can stop it. There is already a plan
 * to resolve against, unlike `CreateTestPlan`.
 */
final class CreateBuild
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, notes?: string|null, is_active?: bool, is_open?: bool, release_date?: string|null}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException when the name is empty or already taken here
     */
    public function __invoke(User $user, TestPlan $plan, array $attributes): Build
    {
        Gate::forUser($user)->authorize(Ability::ManageBuilds->value, $plan);

        $attributes['name'] = trim($attributes['name']);

        if ($attributes['name'] === '') {
            throw ValidationException::withMessages([
                'name' => 'A build needs a name.',
            ]);
        }

        if (Build::query()->forPlan($plan)->where('name', $attributes['name'])->exists()) {
            throw ValidationException::withMessages([
                'name' => 'This plan already has a build with that name.',
            ]);
        }

        $build = new Build;
        $build->fill($attributes);
        $build->testPlan()->associate($plan);
        $build->author_id = $user->getKey();

        $properties = $this->audit->changes($build);

        $build->save();

        $this->audit->record(AuditAction::BuildCreated, $user, $build, $properties);

        return $build;
    }
}
