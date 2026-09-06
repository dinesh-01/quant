<?php

namespace App\Reports;

use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\Execution;
use App\Models\TestPlan;

/**
 * Completed executions grouped by day for a plan and build.
 *
 * This is activity over time, not the latest-per-item score used by status
 * reports. Every completed run on that day counts; drafts do not.
 */
final class ExecutionStatusOverTime
{
    /**
     * @return list<array{date: string, passed: int, failed: int, blocked: int, total: int}>
     */
    public function __invoke(TestPlan $plan, ?Build $build): array
    {
        if ($build === null) {
            return [];
        }

        $rows = Execution::query()
            ->where('test_plan_id', $plan->id)
            ->where('build_id', $build->id)
            ->where('is_draft', false)
            ->whereNotNull('executed_at')
            ->selectRaw('DATE(executed_at) as day, status, COUNT(*) as aggregate')
            ->groupByRaw('DATE(executed_at), status')
            ->orderBy('day')
            ->get();

        $days = [];

        foreach ($rows as $row) {
            $day = (string) $row->day;

            if (! isset($days[$day])) {
                $days[$day] = [
                    'date' => $day,
                    ExecutionStatus::Passed->value => 0,
                    ExecutionStatus::Failed->value => 0,
                    ExecutionStatus::Blocked->value => 0,
                    'total' => 0,
                ];
            }

            $status = $row->status instanceof ExecutionStatus
                ? $row->status->value
                : (string) $row->status;

            if (! isset($days[$day][$status])) {
                continue;
            }

            $count = (int) $row->aggregate;
            $days[$day][$status] += $count;
            $days[$day]['total'] += $count;
        }

        return array_values(array_map(fn (array $day): array => [
            'date' => $day['date'],
            'passed' => $day[ExecutionStatus::Passed->value],
            'failed' => $day[ExecutionStatus::Failed->value],
            'blocked' => $day[ExecutionStatus::Blocked->value],
            'total' => $day['total'],
        ], $days));
    }
}
