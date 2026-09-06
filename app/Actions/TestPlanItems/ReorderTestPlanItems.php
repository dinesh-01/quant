<?php

namespace App\Actions\TestPlanItems;

use App\Enums\Ability;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Renumbers a plan's items into the given order.
 *
 * Not recorded in the audit trail: presentation only, the same judgement the
 * specification reorders made. `sort_order` is not unique so this can happen
 * in one pass.
 */
final class ReorderTestPlanItems
{
    /**
     * @param  array<int, int>  $orderedIds  every item id on the plan, in the order wanted
     *
     * @throws AuthorizationException
     * @throws ValidationException when the ids are not exactly the plan's items
     */
    public function __invoke(User $user, TestPlan $plan, array $orderedIds): void
    {
        Gate::forUser($user)->authorize(Ability::PlanTestCases->value, $plan);

        $itemIds = TestPlanItem::query()
            ->where('test_plan_id', $plan->getKey())
            ->pluck('id')
            ->all();

        sort($itemIds);
        $given = $orderedIds;
        sort($given);

        if ($given !== $itemIds) {
            throw ValidationException::withMessages([
                'order' => 'The order must list every item on this plan exactly once.',
            ]);
        }

        DB::transaction(function () use ($orderedIds): void {
            foreach (array_values($orderedIds) as $position => $id) {
                TestPlanItem::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }
}
