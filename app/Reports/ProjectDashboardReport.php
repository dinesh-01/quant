<?php

namespace App\Reports;

use App\Models\Build;
use App\Models\TestPlan;
use Illuminate\Support\Collection;

/**
 * Status of every plan in a project, using each plan's newest build.
 */
final class ProjectDashboardReport
{
    public function __construct(private readonly PlanStatusReport $planStatusReport) {}

    /**
     * @param  Collection<int, TestPlan>  $plans
     * @return list<array{
     *     id: int,
     *     name: string,
     *     items: int,
     *     build: array{id: int, name: string}|null,
     *     counts: array{passed: int, failed: int, blocked: int, not_run: int}
     * }>
     */
    public function __invoke(Collection $plans): array
    {
        $plans->loadMissing('builds', 'items');

        return $plans
            ->sortBy('name')
            ->values()
            ->map(function (TestPlan $plan): array {
                $build = $plan->builds->sortByDesc('id')->first();
                $status = ($this->planStatusReport)($plan, $build instanceof Build ? $build : null);

                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'items' => $status['total'],
                    'build' => $status['build'],
                    'counts' => $status['counts'],
                ];
            })
            ->all();
    }
}
