import { Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { SegControl } from '@/components/chrome/stat-card';
import { show as executeShow } from '@/routes/executions';
import type { ExecutionListItem } from '@/types/execution';
import { cn } from '@/lib/utils';

export function statusDot(status: string | null): string {
    switch (status) {
        case 'passed':
            return 'bg-success';
        case 'failed':
            return 'bg-destructive';
        case 'blocked':
            return 'bg-warning';
        case 'in_progress':
            return 'bg-info';
        default:
            return 'bg-[#d3d7df]';
    }
}

const compactLabel: Record<string, string> = {
    passed: 'Pass',
    failed: 'Fail',
    blocked: 'Blk',
    in_progress: 'Now',
};

const compactTone: Record<string, string> = {
    passed: 'border-success-border bg-success-bg text-success',
    failed: 'border-destructive-border bg-destructive-bg text-destructive',
    blocked: 'border-warning-border bg-warning-bg text-warning',
    in_progress: 'border-info-border bg-info-bg text-info',
};

export function RunCaseList({
    planId,
    buildId,
    items,
    activeId,
    remaining,
}: {
    planId: number;
    buildId: number | null;
    items: ExecutionListItem[];
    activeId?: number;
    remaining: number;
}) {
    const [filter, setFilter] = useState<'all' | 'untested' | 'failed'>('all');
    const visible = useMemo(() => {
        if (filter === 'untested') {
            return items.filter((item) => item.latest_status === null);
        }

        if (filter === 'failed') {
            return items.filter((item) => item.latest_status === 'failed');
        }

        return items;
    }, [filter, items]);

    return (
        <div className="bg-card relative z-10 flex max-h-[calc(100vh-190px)] scroll-mt-[72px] flex-col self-start overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)]">
            <div className="flex items-center justify-between border-b px-3.5 py-3">
                <h3 className="text-[13px] font-bold">Assigned cases</h3>
                <span className="text-text-subtle text-xs">
                    {remaining} left
                </span>
            </div>
            <div className="relative z-10 border-b px-3 py-2.5">
                <SegControl
                    className="flex w-full"
                    items={[
                        {
                            label: 'All',
                            active: filter === 'all',
                            onClick: () => setFilter('all'),
                        },
                        {
                            label: 'Untested',
                            active: filter === 'untested',
                            onClick: () => setFilter('untested'),
                        },
                        {
                            label: 'Failed',
                            active: filter === 'failed',
                            onClick: () => setFilter('failed'),
                        },
                    ]}
                />
            </div>
            <div className="max-h-[calc(100vh-250px)] overflow-y-auto p-1.5">
                {visible.length === 0 ? (
                    <p className="text-muted-foreground px-3 py-6 text-center text-[13px]">
                        No cases match this filter.
                    </p>
                ) : (
                    visible.map((item) => {
                        const href =
                            buildId === null
                                ? '#'
                                : executeShow.url([planId, item.id], {
                                      query: { build: buildId },
                                  });
                        const running =
                            activeId === item.id && item.latest_status === null;
                        const status = running
                            ? 'in_progress'
                            : item.latest_status;

                        return (
                            <Link
                                key={item.id}
                                href={href}
                                className={cn(
                                    'flex items-center gap-2.5 rounded-lg border border-transparent px-3 py-2.5',
                                    activeId === item.id
                                        ? 'bg-primary-50 border-primary-100'
                                        : 'hover:bg-muted',
                                )}
                            >
                                <span
                                    className={cn(
                                        'size-[9px] shrink-0 rounded-full',
                                        statusDot(status),
                                    )}
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="text-text-subtle block font-mono text-[11px]">
                                        {item.full_external_id}
                                    </span>
                                    <span className="block truncate text-[13px] font-semibold">
                                        {item.name}
                                    </span>
                                </span>
                                {status && (
                                    <span
                                        className={cn(
                                            'inline-flex rounded-full border px-[7px] py-px text-[11px] font-semibold',
                                            compactTone[status] ??
                                                'text-neutral bg-neutral-bg border-neutral-border',
                                        )}
                                    >
                                        {compactLabel[status] ?? status}
                                    </span>
                                )}
                            </Link>
                        );
                    })
                )}
            </div>
        </div>
    );
}
