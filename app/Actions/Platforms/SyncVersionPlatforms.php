<?php

namespace App\Actions\Platforms;

use App\Actions\Audit\AuditLogger;
use App\Concerns\ResolvesProjectPlatforms;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestCaseVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Sets which platforms a test case version is written for.
 *
 * This rides on `manage_test_cases`, not `manage_platforms`: tagging a
 * revision is editing the case, the same way filling in a custom field is.
 * Curating the vocabulary stays a separate job.
 */
final class SyncVersionPlatforms
{
    use ResolvesProjectPlatforms;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int>  $platformIds
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, TestCaseVersion $version, array $platformIds): void
    {
        $version->loadMissing('testCase.testProject');

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $version->testCase->testProject);

        $platforms = $this->resolvePlatforms($version->testCase->testProject, $platformIds);

        foreach ($platforms as $platform) {
            if (! $platform->enable_on_design) {
                throw ValidationException::withMessages([
                    'platforms' => 'That platform is not enabled on design.',
                ]);
            }
        }

        $alreadyAssigned = $version->platforms()->pluck('platforms.id');

        foreach ($platforms as $platform) {
            if ($alreadyAssigned->contains($platform->getKey())) {
                continue;
            }

            if (! $platform->is_open) {
                throw ValidationException::withMessages([
                    'platforms' => 'A closed platform cannot be assigned to a version.',
                ]);
            }
        }

        DB::transaction(function () use ($user, $version, $platforms): void {
            $before = $version->platforms()->pluck('name', 'platforms.id');

            $changes = $version->platforms()->sync($platforms->modelKeys());

            if ($changes['attached'] === [] && $changes['detached'] === []) {
                return;
            }

            $case = $version->testCase;

            $this->audit->record(AuditAction::TestCaseVersionPlatformsChanged, $user, $case, [
                'name' => $case->name,
                'version' => $version->version,
                'added' => $platforms
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
