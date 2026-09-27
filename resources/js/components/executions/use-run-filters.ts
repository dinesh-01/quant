import { useMemo, useState } from 'react';
import type { ExecutionListItem } from '@/types/execution';

export type RunFilterState = {
    suite: string | null;
    priority: string | null;
    assignee: 'all' | 'me';
    keyword: string | null;
    platform: string | null;
};

export function useRunFilters(items: ExecutionListItem[]) {
    const hasAssigned = items.some((item) => item.assigned_to_viewer);
    const [filters, setFilters] = useState<RunFilterState>({
        suite: null,
        priority: null,
        assignee: hasAssigned ? 'me' : 'all',
        keyword: null,
        platform: null,
    });

    const options = useMemo(
        () => ({
            suites: unique(items.map((item) => item.suite)),
            priorities: unique(items.map((item) => item.priority)),
            keywords: unique(items.flatMap((item) => item.keywords)),
            platforms: unique(
                items
                    .map((item) => item.platform)
                    .filter((name): name is string => name !== null),
            ),
        }),
        [items],
    );

    const visible = useMemo(
        () =>
            items.filter((item) => {
                if (filters.suite !== null && item.suite !== filters.suite) {
                    return false;
                }

                if (
                    filters.priority !== null &&
                    item.priority !== filters.priority
                ) {
                    return false;
                }

                if (filters.assignee === 'me' && !item.assigned_to_viewer) {
                    return false;
                }

                if (
                    filters.keyword !== null &&
                    !item.keywords.includes(filters.keyword)
                ) {
                    return false;
                }

                if (
                    filters.platform !== null &&
                    item.platform !== filters.platform
                ) {
                    return false;
                }

                return true;
            }),
        [filters, items],
    );

    const active =
        filters.suite !== null ||
        filters.priority !== null ||
        filters.assignee === 'me' ||
        filters.keyword !== null ||
        filters.platform !== null;

    return {
        filters,
        setFilters,
        options,
        visible,
        active,
        clear: () =>
            setFilters({
                suite: null,
                priority: null,
                assignee: 'all',
                keyword: null,
                platform: null,
            }),
    };
}

function unique(values: string[]): string[] {
    return [...new Set(values.filter(Boolean))].sort((left, right) =>
        left.localeCompare(right),
    );
}
