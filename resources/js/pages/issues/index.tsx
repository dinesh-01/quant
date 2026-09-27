import { Head, setLayoutProps } from '@inertiajs/react';
import { Fragment, useMemo, useState } from 'react';
import { Sparkles } from 'lucide-react';
import { PageHead } from '@/components/chrome/page-head';
import { SegControl, StatCard, StatusPill, Tag } from '@/components/chrome/stat-card';
import { index as issuesIndex } from '@/routes/issues';

type IssueRow = {
    id: number;
    issue_id: string;
    issue_url: string | null;
    issue_status: string | null;
    issue_summary: string | null;
    plan: string;
    case: string;
    external_id: string;
    suite: string | null;
};

type Props = {
    project: { id: number; name: string };
    issues: IssueRow[];
};

function issueTone(status: string | null): string {
    const key = (status ?? '').toLowerCase();

    if (key.includes('open')) {
        return 'failed';
    }

    if (key.includes('progress') || key.includes('triage')) {
        return 'running';
    }

    if (key.includes('resolved') || key.includes('closed')) {
        return 'passed';
    }

    return 'untested';
}

export default function IssuesIndex({ project, issues }: Props) {
    const [filter, setFilter] = useState<'all' | 'open'>('all');

    setLayoutProps({
        breadcrumbs: [{ title: 'Issues', href: issuesIndex(project.id) }],
    });

    const open = issues.filter((issue) =>
        (issue.issue_status ?? '').toLowerCase().includes('open'),
    ).length;
    const visible = useMemo(() => {
        if (filter === 'open') {
            return issues.filter((issue) =>
                (issue.issue_status ?? '').toLowerCase().includes('open'),
            );
        }

        return issues;
    }, [filter, issues]);

    const grouped = useMemo(() => {
        const buckets = new Map<string, IssueRow[]>();

        for (const issue of visible) {
            const key = issue.suite ?? 'Ungrouped';
            const list = buckets.get(key) ?? [];
            list.push(issue);
            buckets.set(key, list);
        }

        return [...buckets.entries()];
    }, [visible]);

    return (
        <>
            <Head title={`${project.name} issues`} />
            <div className="flex-1 space-y-5 p-6">
                <PageHead
                    title="Issues"
                    description={`Defects linked from failed executions — where they sit by suite in ${project.name}.`}
                />

                <div className="grid gap-[18px] xl:grid-cols-[minmax(0,1fr)_372px]">
                    <div className="space-y-4">
                        <div className="grid gap-[18px] md:grid-cols-4">
                            <StatCard
                                label="Total issues"
                                value={issues.length}
                                hint={`linked to ${project.name}`}
                            />
                            <StatCard
                                label="Open"
                                value={open}
                                hint={`${issues.length - open} other`}
                                tone="neutral"
                                valueClassName="text-destructive"
                            />
                            <StatCard
                                label="Missing coverage"
                                value={0}
                                hint="no test case yet"
                                tone="warning"
                            />
                            <StatCard
                                label="Blocking"
                                value={open}
                                hint="failed or blocked runs"
                                valueClassName="text-destructive"
                            />
                        </div>

                        <SegControl
                            items={[
                                {
                                    label: `All ${issues.length}`,
                                    active: filter === 'all',
                                    onClick: () => setFilter('all'),
                                },
                                {
                                    label: `Open ${open}`,
                                    active: filter === 'open',
                                    onClick: () => setFilter('open'),
                                },
                            ]}
                        />

                        <div className="bg-card overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                            <table className="w-full text-[13.5px]">
                                <thead className="bg-muted text-muted-foreground text-left text-xs font-semibold tracking-[0.04em] uppercase">
                                    <tr>
                                        <th className="px-4 py-2.5">Issue</th>
                                        <th className="px-4 py-2.5">
                                            Linked case
                                        </th>
                                        <th className="px-4 py-2.5">Plan</th>
                                        <th className="px-4 py-2.5">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {visible.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="text-muted-foreground px-4 py-10 text-center"
                                            >
                                                No issues have been linked yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        grouped.map(([suite, rows]) => (
                                            <Fragment key={suite}>
                                                <tr className="bg-muted">
                                                    <td
                                                        colSpan={4}
                                                        className="px-4 py-2 text-[12.5px] font-bold"
                                                    >
                                                        {suite}
                                                        <span className="text-text-subtle ml-1.5 font-semibold">
                                                            {rows.length} issues
                                                        </span>
                                                    </td>
                                                </tr>
                                                {rows.map((issue) => (
                                                    <tr
                                                        key={issue.id}
                                                        className="border-border border-t"
                                                    >
                                                        <td className="px-4 py-3">
                                                            <div className="font-semibold">
                                                                {issue.issue_summary ??
                                                                    issue.issue_id}
                                                            </div>
                                                            <div className="text-text-subtle font-mono text-xs">
                                                                {issue.issue_url ? (
                                                                    <a
                                                                        href={
                                                                            issue.issue_url
                                                                        }
                                                                        className="text-primary-700 hover:underline"
                                                                    >
                                                                        {
                                                                            issue.issue_id
                                                                        }
                                                                    </a>
                                                                ) : (
                                                                    issue.issue_id
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div>
                                                                {issue.case}
                                                            </div>
                                                            <div className="text-primary-700 font-mono text-xs">
                                                                {
                                                                    issue.external_id
                                                                }
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <Tag tone="neutral">
                                                                {issue.plan}
                                                            </Tag>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <StatusPill
                                                                status={issueTone(
                                                                    issue.issue_status,
                                                                )}
                                                                label={
                                                                    issue.issue_status ??
                                                                    'Unknown'
                                                                }
                                                            />
                                                        </td>
                                                    </tr>
                                                ))}
                                            </Fragment>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <aside className="bg-card sticky top-20 flex h-[calc(100vh-140px)] flex-col overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                        <div className="flex items-center gap-2.5 border-b px-4 py-3.5">
                            <span className="flex size-[30px] items-center justify-center rounded-[9px] bg-linear-to-br from-primary to-primary-glow text-white shadow-[0_5px_16px_color-mix(in_srgb,var(--primary)_45%,transparent)]">
                                <Sparkles className="size-4" />
                            </span>
                            <div>
                                <h3 className="text-[14px] font-bold">
                                    Ask AI
                                </h3>
                                <p className="text-text-subtle text-[11.5px]">
                                    Cluster defects and suggest coverage
                                </p>
                            </div>
                        </div>
                        <div className="text-muted-foreground flex-1 space-y-3 overflow-y-auto p-4 text-[13px]">
                            <p>
                                When an AI provider is added, this dock will
                                group open defects and suggest suites that still
                                need coverage.
                            </p>
                            <p>
                                {open} open issues are currently linked from
                                executions in {project.name}.
                            </p>
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}
