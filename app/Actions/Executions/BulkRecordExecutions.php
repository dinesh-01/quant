<?php

namespace App\Actions\Executions;

use App\Enums\Ability;
use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\Execution;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Completes the same result on many plan items of one build.
 *
 * Each item goes through RecordExecution so assigned-only, closed plan/build
 * and not-run-complete rules stay in one place.
 */
final class BulkRecordExecutions
{
    public function __construct(private readonly RecordExecution $recordExecution) {}

    /**
     * @param  list<int>  $itemIds
     * @return list<Execution>
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(
        User $user,
        TestPlan $plan,
        Build $build,
        array $itemIds,
        ExecutionStatus $status,
    ): array {
        $plan->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ExecuteTests->value, $plan);

        if ($itemIds === []) {
            throw ValidationException::withMessages([
                'item_ids' => 'Select at least one case to complete.',
            ]);
        }

        if ($status === ExecutionStatus::NotRun) {
            throw ValidationException::withMessages([
                'status' => 'Choose a result before completing the run.',
            ]);
        }

        $items = TestPlanItem::query()
            ->where('test_plan_id', $plan->getKey())
            ->whereIn('id', $itemIds)
            ->get();

        if ($items->count() !== count(array_unique($itemIds))) {
            throw ValidationException::withMessages([
                'item_ids' => 'One of those cases is not on this plan.',
            ]);
        }

        $recorded = [];

        foreach ($items as $item) {
            $recorded[] = ($this->recordExecution)($user, $item, $build, [
                'status' => $status,
                'steps' => [],
            ], true);
        }

        return $recorded;
    }
}
