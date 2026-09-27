import type { ReactNode } from 'react';
import { PageHead } from '@/components/chrome/page-head';
import { ProgressMeter } from '@/components/chrome/stat-card';
import { RunSessionActions } from '@/components/executions/run-session-actions';
import type { RunCounts } from '@/types/execution';

export function RunWorkspaceHead({
    title,
    position,
    total,
    assignedToViewer,
    platform,
    counts,
    extra,
    projectId,
    buildName,
}: {
    title: string;
    position?: number;
    total: number;
    assignedToViewer?: boolean;
    platform?: string | null;
    counts: RunCounts;
    extra?: ReactNode;
    projectId: number;
    buildName?: string | null;
}) {
    return (
        <PageHead
            title={title}
            description={
                <>
                    {position !== undefined && total > 0 ? (
                        <>
                            Case{' '}
                            <strong>
                                {position} of {total}
                            </strong>
                        </>
                    ) : total > 0 ? (
                        <>
                            <strong>{counts.not_run}</strong> of {total} left
                        </>
                    ) : (
                        'No cases on this build yet'
                    )}
                    {assignedToViewer ? ' · assigned to you' : null}
                    {platform ? (
                        <>
                            {' '}
                            · <span className="font-mono">{platform}</span>
                        </>
                    ) : null}
                    {extra}
                </>
            }
            actions={
                <div className="flex flex-wrap items-center gap-2.5">
                    <RunSessionActions
                        projectId={projectId}
                        buildName={buildName}
                    />
                    {total > 0 ? (
                        <div className="w-[280px]">
                            <ProgressMeter counts={counts} total={total} />
                        </div>
                    ) : null}
                </div>
            }
        />
    );
}
