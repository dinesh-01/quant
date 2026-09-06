<?php

namespace App\Actions\Platforms;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Renames a platform or edits its flags.
 *
 * A rename carries every assignment with it, because the assignments point at
 * the row rather than the word — the same reason renaming a keyword does.
 */
final class UpdatePlatform
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name?: string, notes?: string|null, enable_on_design?: bool, enable_on_execution?: bool, is_open?: bool}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException when the name is empty or already taken here
     */
    public function __invoke(User $user, Platform $platform, array $attributes): Platform
    {
        $platform->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManagePlatforms->value, $platform->testProject);

        if (array_key_exists('name', $attributes)) {
            $attributes['name'] = trim($attributes['name']);

            if ($attributes['name'] === '') {
                throw ValidationException::withMessages([
                    'name' => 'A platform needs a name.',
                ]);
            }

            $taken = Platform::query()
                ->forProject($platform->testProject)
                ->where('name', $attributes['name'])
                ->whereKeyNot($platform->getKey())
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'name' => 'This project already has a platform with that name.',
                ]);
            }
        }

        $platform->fill($attributes);

        $properties = $this->audit->changes($platform);

        $platform->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::PlatformUpdated, $user, $platform, $properties);
        }

        return $platform;
    }
}
