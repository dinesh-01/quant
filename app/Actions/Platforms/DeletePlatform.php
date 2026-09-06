<?php

namespace App\Actions\Platforms;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Platform;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Removes a platform from a project's vocabulary.
 *
 * Refused while any plan item sits on it. Those rows are the plan's promise
 * to run a version on this environment; cascading them would drop planned
 * cases as a side effect of retiring a label. Design-time and plan-assignment
 * pivots cascade — those are tags, not the plan itself.
 */
final class DeletePlatform
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException when a plan still pins a case on this platform
     */
    public function __invoke(User $user, Platform $platform): void
    {
        $platform->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManagePlatforms->value, $platform->testProject);

        $items = TestPlanItem::query()->where('platform_id', $platform->getKey())->count();

        if ($items > 0) {
            throw ValidationException::withMessages([
                'platform' => 'This platform is still used on a test plan. Unlink those cases first.',
            ]);
        }

        DB::transaction(function () use ($user, $platform): void {
            $this->audit->record(AuditAction::PlatformDeleted, $user, $platform, [
                'name' => $platform->name,
            ]);

            $platform->delete();
        });
    }
}
