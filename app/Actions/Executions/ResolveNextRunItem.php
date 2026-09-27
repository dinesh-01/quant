<?php

namespace App\Actions\Executions;

use App\Enums\Ability;
use App\Models\Build;
use App\Models\Execution;
use App\Models\TesterAssignment;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * The next untested case on this build after the one just submitted.
 */
final class ResolveNextRunItem
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestPlan $plan, Build $build, TestPlanItem $current): ?TestPlanItem
    {
        $plan->loadMissing('testProject');

        $gate = Gate::forUser($user);

        if (
            ! $gate->allows(Ability::ExecuteTests->value, $plan)
            && ! $gate->allows(Ability::ViewExecutions->value, $plan)
        ) {
            throw new AuthorizationException;
        }

        $items = $plan->items()->get();

        if ($gate->allows(Ability::ExecuteOnlyAssignedTestCases->value, $plan)) {
            $assignedIds = TesterAssignment::query()
                ->where('build_id', $build->id)
                ->where('user_id', $user->id)
                ->pluck('test_plan_item_id');

            $items = $items->whereIn('id', $assignedIds)->values();
        }

        if ($items->isEmpty()) {
            return null;
        }

        $completedIds = Execution::query()
            ->where('build_id', $build->id)
            ->whereIn('test_plan_item_id', $items->modelKeys())
            ->where('is_draft', false)
            ->orderByDesc('id')
            ->get()
            ->unique('test_plan_item_id')
            ->pluck('test_plan_item_id');

        $currentIndex = $items->search(
            fn (TestPlanItem $item): bool => $item->id === $current->id,
        );

        if ($currentIndex === false) {
            $currentIndex = -1;
        }

        $count = $items->count();

        for ($offset = 1; $offset <= $count; $offset++) {
            /** @var TestPlanItem $candidate */
            $candidate = $items[($currentIndex + $offset + $count) % $count];

            if ($candidate->id === $current->id) {
                continue;
            }

            if (! $completedIds->contains($candidate->id)) {
                return $candidate;
            }
        }

        return null;
    }
}
