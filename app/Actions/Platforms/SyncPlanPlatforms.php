<?php

namespace App\Actions\Platforms;

use App\Actions\Audit\AuditLogger;
use App\Concerns\ResolvesProjectPlatforms;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Sets which of the project's platforms a plan executes against.
 *
 * Scoped to the plan, so a plan role can govern it. `manage_plan_platforms`
 * is separate from `manage_platforms`: curating the vocabulary and choosing
 * which of it this plan uses are different jobs.
 *
 * Removing a platform deletes the plan items that sat on it — those items
 * named that environment, and leaving them behind would pin cases to a
 * platform the plan no longer claims. Adding the first platform is refused
 * while the plan still has items with no platform: those rows occupy the
 * no-platform slot, and silently splitting them across new environments
 * would invent items nobody asked for.
 */
final class SyncPlanPlatforms
{
    use ResolvesProjectPlatforms;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int>  $platformIds
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, TestPlan $plan, array $platformIds): void
    {
        $plan->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManagePlanPlatforms->value, $plan);

        $platforms = $this->resolvePlatforms($plan->testProject, $platformIds);

        foreach ($platforms as $platform) {
            if (! $platform->enable_on_execution) {
                throw ValidationException::withMessages([
                    'platforms' => 'That platform is not enabled for execution.',
                ]);
            }
        }

        $alreadyAssigned = $plan->platforms()->pluck('platforms.id');

        foreach ($platforms as $platform) {
            if ($alreadyAssigned->contains($platform->getKey())) {
                continue;
            }

            if (! $platform->is_open) {
                throw ValidationException::withMessages([
                    'platforms' => 'A closed platform cannot be added to a plan.',
                ]);
            }
        }

        $addingFirst = $alreadyAssigned->isEmpty() && $platforms->isNotEmpty();

        if ($addingFirst && $plan->items()->whereNull('platform_id')->exists()) {
            throw ValidationException::withMessages([
                'platforms' => 'This plan has cases linked without a platform. Unlink them before adding platforms.',
            ]);
        }

        DB::transaction(function () use ($user, $plan, $platforms): void {
            $before = $plan->platforms()->pluck('name', 'platforms.id');

            $changes = $plan->platforms()->sync($platforms->modelKeys());

            $removedItems = 0;

            if ($changes['detached'] !== []) {
                $removedItems = TestPlanItem::query()
                    ->where('test_plan_id', $plan->getKey())
                    ->whereIn('platform_id', $changes['detached'])
                    ->delete();
            }

            if ($changes['attached'] === [] && $changes['detached'] === []) {
                return;
            }

            $this->audit->record(AuditAction::PlanPlatformsChanged, $user, $plan, [
                'name' => $plan->name,
                'added' => $platforms
                    ->whereIn('id', $changes['attached'])
                    ->pluck('name')
                    ->values()
                    ->all(),
                'removed' => $before
                    ->only($changes['detached'])
                    ->values()
                    ->all(),
                'items_removed' => $removedItems,
            ]);
        });
    }
}
