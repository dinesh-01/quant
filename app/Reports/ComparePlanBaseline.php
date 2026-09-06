<?php

namespace App\Reports;

use App\Models\ReportBaseline;

/**
 * Diffs a live plan-status report against a stored snapshot.
 */
final class ComparePlanBaseline
{
    /**
     * @param  array{counts: array{passed: int, failed: int, blocked: int, not_run: int}, items: list<array{id: int, full_external_id: string, name: string, platform: string|null, status: string}>}  $live
     * @return array{
     *     baseline: array{id: int, name: string, created_at: string},
     *     live_counts: array{passed: int, failed: int, blocked: int, not_run: int},
     *     baseline_counts: array{passed: int, failed: int, blocked: int, not_run: int},
     *     changed: list<array{full_external_id: string, name: string, platform: string|null, live: string|null, baseline: string|null}>
     * }
     */
    public function __invoke(array $live, ReportBaseline $baseline): array
    {
        $liveByKey = [];

        foreach ($live['items'] as $item) {
            $liveByKey[$this->itemKey($item['full_external_id'], $item['platform'])] = $item;
        }

        $baselineByKey = [];

        foreach ($baseline->items as $item) {
            $baselineByKey[$this->itemKey($item['full_external_id'], $item['platform'])] = $item;
        }

        $changed = [];

        foreach (array_unique([...array_keys($liveByKey), ...array_keys($baselineByKey)]) as $key) {
            $liveItem = $liveByKey[$key] ?? null;
            $baselineItem = $baselineByKey[$key] ?? null;
            $liveStatus = $liveItem['status'] ?? null;
            $baselineStatus = $baselineItem['status'] ?? null;

            if ($liveStatus === $baselineStatus) {
                continue;
            }

            $changed[] = [
                'full_external_id' => $liveItem['full_external_id'] ?? $baselineItem['full_external_id'],
                'name' => $liveItem['name'] ?? $baselineItem['name'],
                'platform' => $liveItem['platform'] ?? $baselineItem['platform'] ?? null,
                'live' => $liveStatus,
                'baseline' => $baselineStatus,
            ];
        }

        return [
            'baseline' => [
                'id' => $baseline->id,
                'name' => $baseline->name,
                'created_at' => $baseline->created_at?->toDateTimeString() ?? '',
            ],
            'live_counts' => $live['counts'],
            'baseline_counts' => $baseline->counts,
            'changed' => $changed,
        ];
    }

    private function itemKey(string $externalId, ?string $platform): string
    {
        return $externalId."\0".($platform ?? '');
    }
}
