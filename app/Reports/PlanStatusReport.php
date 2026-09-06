<?php

namespace App\Reports;

use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\TestPlan;
use App\Models\TestPlanItem;

/**
 * How a plan's items stand on one build: latest completed status, or not run.
 */
final class PlanStatusReport
{
    public function __construct(private readonly LatestCompletedRuns $latestCompletedRuns) {}

    /**
     * @return array{
     *     build: array{id: int, name: string}|null,
     *     total: int,
     *     counts: array{passed: int, failed: int, blocked: int, not_run: int},
     *     items: list<array{id: int, full_external_id: string, name: string, platform: string|null, status: string}>
     * }
     */
    public function __invoke(TestPlan $plan, ?Build $build): array
    {
        $plan->loadMissing(['items.testCaseVersion.testCase.testProject', 'items.platform']);

        $latest = $build === null
            ? collect()
            : ($this->latestCompletedRuns)->forBuild($build);

        $counts = [
            ExecutionStatus::Passed->value => 0,
            ExecutionStatus::Failed->value => 0,
            ExecutionStatus::Blocked->value => 0,
            ExecutionStatus::NotRun->value => 0,
        ];

        $items = $plan->items->map(function (TestPlanItem $item) use ($latest, &$counts): array {
            $status = $latest->get($item->id)?->status->value ?? ExecutionStatus::NotRun->value;
            $counts[$status]++;

            return [
                'id' => $item->id,
                'full_external_id' => $item->testCaseVersion->testCase->fullExternalId(),
                'name' => $item->testCaseVersion->testCase->name,
                'platform' => $item->platform?->name,
                'status' => $status,
            ];
        })->values()->all();

        return [
            'build' => $build === null ? null : [
                'id' => $build->id,
                'name' => $build->name,
            ],
            'total' => $plan->items->count(),
            'counts' => $counts,
            'items' => $items,
        ];
    }
}
