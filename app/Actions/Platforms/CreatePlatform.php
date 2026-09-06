<?php

namespace App\Actions\Platforms;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Platform;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Adds a platform to a project's vocabulary.
 *
 * `manage_platforms` is project-scoped, so a team can curate its own list
 * without any standing in another project. Assigning those platforms to a
 * plan is a different ability.
 */
final class CreatePlatform
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, notes?: string|null, enable_on_design?: bool, enable_on_execution?: bool, is_open?: bool}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException when the name is empty or already taken here
     */
    public function __invoke(User $user, TestProject $project, array $attributes): Platform
    {
        Gate::forUser($user)->authorize(Ability::ManagePlatforms->value, $project);

        $attributes['name'] = trim($attributes['name']);

        if ($attributes['name'] === '') {
            throw ValidationException::withMessages([
                'name' => 'A platform needs a name.',
            ]);
        }

        if (Platform::query()->forProject($project)->where('name', $attributes['name'])->exists()) {
            throw ValidationException::withMessages([
                'name' => 'This project already has a platform with that name.',
            ]);
        }

        $platform = new Platform;
        $platform->fill($attributes);
        $platform->testProject()->associate($project);

        $properties = $this->audit->changes($platform);

        $platform->save();

        $this->audit->record(AuditAction::PlatformCreated, $user, $platform, $properties);

        return $platform;
    }
}
