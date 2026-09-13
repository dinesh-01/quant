<?php

namespace App\Actions\Executions;

use App\Enums\Ability;
use App\Enums\ExecutionStatus;
use App\Jobs\ProcessReportedExecutions;
use App\Models\Build;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Authorizes execute_tests on the plan, then queues the resolved reports.
 *
 * Resolution and 422s happen before this action so the HTTP response can reject
 * a bad payload without waiting for a worker. The job re-runs RecordExecution
 * so assigned-only and closed plan/build rules stay in one place.
 */
final class QueueReportedExecutions
{
    /**
     * @param  list<array{item: TestPlanItem, build: Build, attributes: array{status: ExecutionStatus, notes: string|null, duration: string|null, steps: list<array{test_case_step_id: int, status: ExecutionStatus, notes: string|null}>}, complete: bool}>  $resolved
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestPlan $plan, array $resolved): int
    {
        $plan->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ExecuteTests->value, $plan);

        ProcessReportedExecutions::dispatch($user->id, array_map($this->serialize(...), $resolved));

        return count($resolved);
    }

    /**
     * @param  array{item: TestPlanItem, build: Build, attributes: array{status: ExecutionStatus, notes: string|null, duration: string|null, steps: list<array{test_case_step_id: int, status: ExecutionStatus, notes: string|null}>}, complete: bool}  $resolved
     * @return array{item_id: int, build_id: int, status: string, notes: string|null, duration: string|null, steps: list<array{test_case_step_id: int, status: string, notes: string|null}>, complete: bool}
     */
    private function serialize(array $resolved): array
    {
        $steps = [];

        foreach ($resolved['attributes']['steps'] as $step) {
            $steps[] = [
                'test_case_step_id' => $step['test_case_step_id'],
                'status' => $step['status']->value,
                'notes' => $step['notes'],
            ];
        }

        return [
            'item_id' => $resolved['item']->id,
            'build_id' => $resolved['build']->id,
            'status' => $resolved['attributes']['status']->value,
            'notes' => $resolved['attributes']['notes'],
            'duration' => $resolved['attributes']['duration'],
            'steps' => $steps,
            'complete' => $resolved['complete'],
        ];
    }
}
