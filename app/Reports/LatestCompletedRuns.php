<?php

namespace App\Reports;

use App\Models\Build;
use App\Models\Execution;
use Illuminate\Support\Collection;

/**
 * The latest completed run of each plan item on one build.
 *
 * Completed means `is_draft` is false. The highest id wins, matching the
 * execute list: a later save replaces an earlier one even if timestamps
 * disagree.
 */
final class LatestCompletedRuns
{
    /**
     * @return Collection<int, Execution> keyed by test_plan_item_id
     */
    public function forBuild(Build $build): Collection
    {
        return Execution::query()
            ->where('build_id', $build->id)
            ->where('is_draft', false)
            ->whereIn('id', function ($query) use ($build): void {
                $query->selectRaw('MAX(id)')
                    ->from('executions')
                    ->where('build_id', $build->id)
                    ->where('is_draft', false)
                    ->groupBy('test_plan_item_id');
            })
            ->get()
            ->keyBy('test_plan_item_id');
    }
}
