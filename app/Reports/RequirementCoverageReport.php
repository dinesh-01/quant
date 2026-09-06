<?php

namespace App\Reports;

use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\Requirement;
use App\Models\RequirementCoverage;
use App\Models\TestPlan;

/**
 * Project requirements versus the versions on this plan, scored from latest runs.
 */
final class RequirementCoverageReport
{
    public function __construct(private readonly LatestCompletedRuns $latestCompletedRuns) {}

    /**
     * @return array{
     *     covered: list<array{id: int, doc_id: string, name: string, covering_items: int, passed_items: int, status: string}>,
     *     uncovered: list<array{id: int, doc_id: string, name: string}>
     * }
     */
    public function __invoke(TestPlan $plan, ?Build $build): array
    {
        $plan->loadMissing('testProject', 'items');

        $itemIdsByVersion = $plan->items
            ->groupBy('test_case_version_id')
            ->map(fn ($items) => $items->pluck('id')->all());

        $versionIds = $itemIdsByVersion->keys()->all();

        $latest = $build === null
            ? collect()
            : ($this->latestCompletedRuns)->forBuild($build);

        $coverages = $versionIds === []
            ? collect()
            : RequirementCoverage::query()
                ->whereIn('test_case_version_id', $versionIds)
                ->with('requirementVersion.requirement')
                ->get();

        $coveredIds = [];
        $rows = [];

        foreach ($coverages as $coverage) {
            $requirement = $coverage->requirementVersion->requirement;
            $id = $requirement->id;
            $itemIds = $itemIdsByVersion->get($coverage->test_case_version_id, []);

            if (! isset($rows[$id])) {
                $rows[$id] = [
                    'id' => $id,
                    'doc_id' => $requirement->doc_id,
                    'name' => $requirement->name,
                    'item_ids' => [],
                ];
            }

            foreach ($itemIds as $itemId) {
                $rows[$id]['item_ids'][$itemId] = true;
            }

            $coveredIds[$id] = true;
        }

        $covered = array_values(array_map(function (array $row) use ($latest): array {
            $itemIds = array_keys($row['item_ids']);
            $statuses = [];

            foreach ($itemIds as $itemId) {
                $statuses[] = $latest->get($itemId)?->status->value ?? ExecutionStatus::NotRun->value;
            }

            $passed = count(array_filter(
                $statuses,
                fn (string $status): bool => $status === ExecutionStatus::Passed->value,
            ));

            return [
                'id' => $row['id'],
                'doc_id' => $row['doc_id'],
                'name' => $row['name'],
                'covering_items' => count($itemIds),
                'passed_items' => $passed,
                'status' => $this->rollup($statuses),
            ];
        }, $rows));

        usort($covered, fn (array $a, array $b): int => [$a['doc_id'], $a['name']] <=> [$b['doc_id'], $b['name']]);

        $uncovered = Requirement::query()
            ->where('test_project_id', $plan->test_project_id)
            ->whereKeyNot(array_keys($coveredIds) ?: [0])
            ->orderBy('doc_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Requirement $requirement): array => [
                'id' => $requirement->id,
                'doc_id' => $requirement->doc_id,
                'name' => $requirement->name,
            ])
            ->all();

        return [
            'covered' => $covered,
            'uncovered' => $uncovered,
        ];
    }

    /**
     * @param  list<string>  $statuses
     */
    private function rollup(array $statuses): string
    {
        if (in_array(ExecutionStatus::Passed->value, $statuses, true)) {
            return ExecutionStatus::Passed->value;
        }

        if (in_array(ExecutionStatus::Failed->value, $statuses, true)) {
            return ExecutionStatus::Failed->value;
        }

        if (in_array(ExecutionStatus::Blocked->value, $statuses, true)) {
            return ExecutionStatus::Blocked->value;
        }

        return ExecutionStatus::NotRun->value;
    }
}
