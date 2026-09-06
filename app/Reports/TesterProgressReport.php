<?php

namespace App\Reports;

use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\TesterAssignment;
use App\Models\User;

/**
 * Assigned items per tester on one build, scored from the latest completed run.
 */
final class TesterProgressReport
{
    public function __construct(private readonly LatestCompletedRuns $latestCompletedRuns) {}

    /**
     * @return list<array{user_id: int, name: string, assigned: int, passed: int, failed: int, blocked: int, not_run: int}>
     */
    public function __invoke(Build $build): array
    {
        $assignments = TesterAssignment::query()
            ->where('build_id', $build->id)
            ->with('user')
            ->orderBy('id')
            ->get();

        $latest = ($this->latestCompletedRuns)->forBuild($build);

        $byUser = [];

        foreach ($assignments as $assignment) {
            $user = $assignment->user;

            if (! $user instanceof User) {
                continue;
            }

            $id = $user->id;

            if (! isset($byUser[$id])) {
                $byUser[$id] = [
                    'user_id' => $id,
                    'name' => $user->name,
                    'assigned' => 0,
                    ExecutionStatus::Passed->value => 0,
                    ExecutionStatus::Failed->value => 0,
                    ExecutionStatus::Blocked->value => 0,
                    ExecutionStatus::NotRun->value => 0,
                ];
            }

            $status = $latest->get($assignment->test_plan_item_id)?->status->value
                ?? ExecutionStatus::NotRun->value;

            $byUser[$id]['assigned']++;
            $byUser[$id][$status]++;
        }

        return array_values(array_map(function (array $row): array {
            return [
                'user_id' => $row['user_id'],
                'name' => $row['name'],
                'assigned' => $row['assigned'],
                'passed' => $row[ExecutionStatus::Passed->value],
                'failed' => $row[ExecutionStatus::Failed->value],
                'blocked' => $row[ExecutionStatus::Blocked->value],
                'not_run' => $row[ExecutionStatus::NotRun->value],
            ];
        }, $byUser));
    }
}
