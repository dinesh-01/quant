<?php

namespace App\Jobs;

use App\Actions\Executions\RecordExecution;
use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;

/**
 * Persists CI execution results after the API has resolved and authorized them.
 *
 * Item and build ids are stored as primitives so a deleted row between dispatch
 * and handle is a skip, not a serialized-model failure. Per-item validation
 * (closed build, assigned-only) is logged and skipped so one bad row does not
 * retry the whole batch.
 */
final class ProcessReportedExecutions implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [1, 5, 10];

    public int $timeout = 60;

    /**
     * @param  list<array{item_id: int, build_id: int, status: string, notes: string|null, duration: string|null, steps: list<array{test_case_step_id: int, status: string, notes: string|null}>, complete: bool}>  $reports
     */
    public function __construct(
        public int $userId,
        public array $reports,
    ) {}

    public function handle(RecordExecution $recordExecution): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null || ! $user->isActive()) {
            return;
        }

        foreach ($this->reports as $report) {
            $item = TestPlanItem::query()->find($report['item_id']);
            $build = Build::query()->find($report['build_id']);

            if ($item === null || $build === null) {
                continue;
            }

            try {
                $recordExecution(
                    $user,
                    $item,
                    $build,
                    $this->attributes($report),
                    $report['complete'],
                );
            } catch (AuthorizationException|ValidationException) {
                continue;
            }
        }
    }

    /**
     * @param  array{item_id: int, build_id: int, status: string, notes: string|null, duration: string|null, steps: list<array{test_case_step_id: int, status: string, notes: string|null}>, complete: bool}  $report
     * @return array{status: ExecutionStatus, notes: string|null, duration: string|null, steps: list<array{test_case_step_id: int, status: ExecutionStatus, notes: string|null}>}
     */
    private function attributes(array $report): array
    {
        $steps = [];

        foreach ($report['steps'] as $step) {
            $steps[] = [
                'test_case_step_id' => $step['test_case_step_id'],
                'status' => ExecutionStatus::from($step['status']),
                'notes' => $step['notes'],
            ];
        }

        return [
            'status' => ExecutionStatus::from($report['status']),
            'notes' => $report['notes'],
            'duration' => $report['duration'],
            'steps' => $steps,
        ];
    }
}
