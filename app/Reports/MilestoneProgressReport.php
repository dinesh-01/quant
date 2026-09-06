<?php

namespace App\Reports;

use App\Enums\ExecutionStatus;
use App\Enums\TestCaseUrgency;
use App\Models\Build;
use App\Models\Execution;
use App\Models\Milestone;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use Illuminate\Support\Collection;

/**
 * Milestone targets versus passed latest runs, split by item urgency.
 */
final class MilestoneProgressReport
{
    public function __construct(private readonly LatestCompletedRuns $latestCompletedRuns) {}

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     target_date: string,
     *     bands: array<string, array{items: int, passed: int, actual_percent: int, target_percent: int}>
     * }>
     */
    public function __invoke(TestPlan $plan, ?Build $build): array
    {
        $plan->loadMissing('milestones', 'items');

        $latest = $build === null
            ? collect()
            : ($this->latestCompletedRuns)->forBuild($build);

        $items = $plan->items;

        return $plan->milestones
            ->sortBy('target_date')
            ->values()
            ->map(function (Milestone $milestone) use ($items, $latest): array {
                return [
                    'id' => $milestone->id,
                    'name' => $milestone->name,
                    'target_date' => $milestone->target_date->toDateString(),
                    'bands' => [
                        'high' => $this->band($items, $latest, TestCaseUrgency::High, $milestone->high_percent),
                        'medium' => $this->band($items, $latest, TestCaseUrgency::Medium, $milestone->medium_percent),
                        'low' => $this->band($items, $latest, TestCaseUrgency::Low, $milestone->low_percent),
                    ],
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, TestPlanItem>  $items
     * @param  Collection<int, Execution>  $latest
     * @return array{items: int, passed: int, actual_percent: int, target_percent: int}
     */
    private function band($items, $latest, TestCaseUrgency $urgency, int $target): array
    {
        $band = $items->filter(fn (TestPlanItem $item): bool => $item->urgency === $urgency);
        $total = $band->count();
        $passed = $band->filter(function (TestPlanItem $item) use ($latest): bool {
            return $latest->get($item->id)?->status === ExecutionStatus::Passed;
        })->count();

        return [
            'items' => $total,
            'passed' => $passed,
            'actual_percent' => $total === 0 ? 0 : (int) round(($passed / $total) * 100),
            'target_percent' => $target,
        ];
    }
}
