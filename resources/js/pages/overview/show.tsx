import { Head, Link, setLayoutProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { MockIcon } from '@/components/chrome/mock-icon';
import { PageHead } from '@/components/chrome/page-head';
import {
    AvatarStack,
    PriorityMark,
    ProgressMeter,
    ResultDonut,
    StatCard,
    StatusPill,
    Tag,
    UserAvatar,
} from '@/components/chrome/stat-card';
import { Button } from '@/components/ui/button';
import { index as executeIndex, show as executeShow } from '@/routes/executions';
import { index as selectorIndex } from '@/routes/plan-selector';
import { index as planIndex } from '@/routes/plans';
import { show as projectOverview } from '@/routes/projects';

type Counts = {
    passed: number;
    failed: number;
    blocked: number;
    not_run: number;
};

type OverviewProps = {
    project: { id: number; name: string; prefix: string };
    viewer: string;
    overview: {
        greeting: string;
        cases: number;
        cases_this_week: number;
        automated: number;
        active_runs: number;
        runs_in_progress: number;
        runs_blocked: number;
        pass_rate: number | null;
        previous_build: { name: string; delta: number } | null;
        latest_build: { name: string; counts: Counts; total: number } | null;
        runs: {
            id: number;
            external_id: string | null;
            name: string;
            build: string | null;
            items: number;
            counts: Counts | null;
            assignees: string[];
        }[];
        needs_attention: {
            id: number;
            name: string;
            external_id: string;
            plan: string;
            plan_id: number;
            item_id: number;
            status: string;
            priority: string;
            tester: string | null;
            issue: string | null;
            issue_url: string | null;
            issue_note: string | null;
        }[];
        activity: {
            id: number;
            actor: string | null;
            is_viewer: boolean;
            verb: string;
            token: string | null;
            token_style: 'mono' | 'em' | 'tag' | null;
            tail: string | null;
            at: string | null;
            when: string | null;
        }[];
    };
};

function firstNameOf(name: string): string {
    return name.split(' ')[0] ?? name;
}

function buildLabel(name: string): string {
    return /^build\b/i.test(name) ? name : `Build ${name}`;
}

/**
 * The highlighted word in an activity line: a case or version number reads as
 * code, a suite as its own name, a build as the tag it is shown as elsewhere.
 */
function ActivityToken({
    style,
    children,
}: {
    style: 'mono' | 'em' | 'tag' | null;
    children: ReactNode;
}) {
    if (style === 'tag') {
        return <Tag>{children}</Tag>;
    }

    if (style === 'em') {
        return <em>{children}</em>;
    }

    return (
        <span className="text-text-subtle font-mono text-[12.5px]">
            {children}
        </span>
    );
}

export default function ProjectOverview({
    project,
    viewer,
    overview,
}: OverviewProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Overview', href: projectOverview(project.id) },
        ],
    });

    const firstName = firstNameOf(viewer);
    const automationPct =
        overview.cases === 0
            ? 0
            : Math.round((overview.automated / overview.cases) * 100);
    const inProgress = overview.runs_in_progress ?? 0;

    return (
        <>
            <Head title={`${project.name} overview`} />
            <div className="flex flex-1 flex-col p-6">
                <PageHead
                    title={`${overview.greeting}, ${firstName} 👋`}
                    description={
                        <>
                            Here's where the <strong>{project.name}</strong> QA
                            cycle stands today
                            {inProgress > 0
                                ? ` — ${inProgress} runs are still in progress.`
                                : '.'}
                        </>
                    }
                    actions={
                        <Button asChild>
                            <Link href={selectorIndex(project.id)}>
                                <MockIcon name="play" />
                                Start a run
                            </Link>
                        </Button>
                    }
                />

                <div className="mb-[18px] grid gap-[18px] md:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Total test cases"
                        value={overview.cases}
                        icon={<MockIcon name="cases" className="size-[15px]" />}
                        badgeIcon={<MockIcon name="cases" className="size-[18px]" />}
                        hint={
                            overview.cases_this_week > 0 ? (
                                <>
                                    <MockIcon
                                        name="trend-up"
                                        className="mr-1 inline size-[13px] align-[-2px]"
                                    />
                                    +{overview.cases_this_week} this week
                                </>
                            ) : (
                                'No new cases this week'
                            )
                        }
                        hintTone={
                            overview.cases_this_week > 0 ? 'up' : 'muted'
                        }
                    />
                    <StatCard
                        label="Automation coverage"
                        value={`${automationPct}%`}
                        icon={<MockIcon name="git" className="size-[15px]" />}
                        badgeIcon={
                            <MockIcon name="target" className="size-[18px]" />
                        }
                        tone="success"
                        hint={`${overview.automated} of ${overview.cases} cases automated`}
                        hintTone="up"
                    />
                    <StatCard
                        label="Active runs"
                        value={overview.active_runs}
                        icon={<MockIcon name="play" className="size-[15px]" />}
                        badgeIcon={<MockIcon name="zap" className="size-[18px]" />}
                        tone="info"
                        hint={
                            overview.active_runs === 0
                                ? 'No runs in progress'
                                : `${overview.runs_in_progress} in progress${overview.runs_blocked > 0 ? ` · ${overview.runs_blocked} blocked` : ''}`
                        }
                    />
                    <StatCard
                        label={`Pass rate${overview.latest_build ? ` · ${buildLabel(overview.latest_build.name)}` : ''}`}
                        value={
                            overview.pass_rate === null
                                ? '—'
                                : `${overview.pass_rate}%`
                        }
                        icon={<MockIcon name="chart" className="size-[15px]" />}
                        badgeIcon={
                            <MockIcon name="check" className="size-[18px]" />
                        }
                        tone="success"
                        hint={
                            overview.previous_build
                                ? overview.previous_build.delta === 0
                                    ? `No change vs ${buildLabel(overview.previous_build.name)}`
                                    : `${overview.previous_build.delta < 0 ? '▼' : '▲'} ${Math.abs(overview.previous_build.delta)}% vs ${buildLabel(overview.previous_build.name)}`
                                : undefined
                        }
                        hintTone={
                            overview.previous_build &&
                            overview.previous_build.delta !== 0
                                ? overview.previous_build.delta < 0
                                    ? 'down'
                                    : 'up'
                                : 'muted'
                        }
                    />
                </div>

                <div className="mb-[18px] grid gap-[18px] xl:grid-cols-3">
                    <div className="bg-card overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)] xl:col-span-2">
                        <div className="flex items-center gap-2.5 border-b px-[18px] py-4">
                            <h3 className="text-[15px] font-bold">
                                Active runs
                            </h3>
                            <span className="text-muted-foreground text-[12.5px]">
                                Execution progress across current plans
                            </span>
                            <Button
                                variant="ghost"
                                size="sm"
                                asChild
                                className="ml-auto"
                            >
                                <Link href={planIndex(project.id)}>
                                    View all plans
                                    <MockIcon name="arrow-right" />
                                </Link>
                            </Button>
                        </div>
                        <table className="w-full text-[13.5px]">
                            <thead className="bg-muted text-muted-foreground text-left text-xs font-semibold tracking-[0.04em] uppercase">
                                <tr>
                                    <th className="px-4 py-[11px]">Run</th>
                                    <th className="px-4 py-[11px]">Build</th>
                                    <th className="w-[34%] px-4 py-[11px]">
                                        Progress
                                    </th>
                                    <th className="px-4 py-[11px]">
                                        Assignees
                                    </th>
                                    <th className="px-4 py-[11px]" />
                                </tr>
                            </thead>
                            <tbody>
                                {overview.runs.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="text-muted-foreground px-4 py-8 text-center"
                                        >
                                            No active runs.
                                        </td>
                                    </tr>
                                ) : (
                                    overview.runs.map((run) => (
                                        <tr
                                            key={run.id}
                                            className="border-border hover:bg-muted border-t"
                                        >
                                            <td className="px-4 py-[13px]">
                                                <div className="font-semibold">
                                                    {run.name}
                                                </div>
                                                <div className="text-muted-foreground font-mono text-xs">
                                                    Plan {run.external_id}
                                                </div>
                                            </td>
                                            <td className="px-4 py-[13px]">
                                                {run.build ? (
                                                    <Tag>
                                                        {buildLabel(run.build)}
                                                    </Tag>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-4 py-[13px]">
                                                {run.counts ? (
                                                    <ProgressMeter
                                                        counts={run.counts}
                                                        total={run.items}
                                                    />
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-4 py-[13px]">
                                                <AvatarStack
                                                    names={run.assignees ?? []}
                                                />
                                            </td>
                                            <td className="px-4 py-[13px] text-right">
                                                <Button asChild size="sm">
                                                    <Link
                                                        href={executeIndex(
                                                            run.id,
                                                        )}
                                                    >
                                                        Resume
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="bg-card overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                        <div className="flex items-center gap-2.5 border-b px-[18px] py-4">
                            <h3 className="text-[15px] font-bold">
                                Latest build result
                            </h3>
                            <span className="text-muted-foreground text-[12.5px]">
                                {overview.latest_build
                                    ? buildLabel(overview.latest_build.name)
                                    : 'No build yet'}
                            </span>
                        </div>
                        <div className="p-[18px]">
                            {overview.latest_build ? (
                                <div className="flex flex-col items-center">
                                    <ResultDonut
                                        counts={overview.latest_build.counts}
                                        total={overview.latest_build.total}
                                        passRate={overview.pass_rate ?? 0}
                                    />
                                    <ul className="mt-3.5 w-full space-y-2.5 text-[13px]">
                                        <li className="flex items-center gap-2.5">
                                            <span className="bg-success size-2.5 rounded-[3px]" />
                                            Passed
                                            <span className="ml-auto font-bold">
                                                {
                                                    overview.latest_build.counts
                                                        .passed
                                                }
                                            </span>
                                        </li>
                                        <li className="flex items-center gap-2.5">
                                            <span className="bg-destructive size-2.5 rounded-[3px]" />
                                            Failed
                                            <span className="ml-auto font-bold">
                                                {
                                                    overview.latest_build.counts
                                                        .failed
                                                }
                                            </span>
                                        </li>
                                        <li className="flex items-center gap-2.5">
                                            <span className="bg-warning size-2.5 rounded-[3px]" />
                                            Blocked
                                            <span className="ml-auto font-bold">
                                                {
                                                    overview.latest_build.counts
                                                        .blocked
                                                }
                                            </span>
                                        </li>
                                        <li className="flex items-center gap-2.5">
                                            <span className="bg-neutral-bg size-2.5 rounded-[3px]" />
                                            Untested
                                            <span className="ml-auto font-bold">
                                                {
                                                    overview.latest_build.counts
                                                        .not_run
                                                }
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">
                                    Run a plan to see results here.
                                </p>
                            )}
                        </div>
                    </div>
                </div>

                <div className="grid gap-[18px] xl:grid-cols-3">
                    <div className="bg-card overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)] xl:col-span-2">
                        <div className="flex items-center gap-2.5 border-b px-[18px] py-4">
                            <h3 className="text-[15px] font-bold">
                                Needs attention
                            </h3>
                            <span className="text-muted-foreground text-[12.5px]">
                                Failing & blocked cases in current runs
                            </span>
                            <Button
                                variant="ghost"
                                size="sm"
                                type="button"
                                className="ml-auto"
                            >
                                <MockIcon name="filter" />
                                Filter
                            </Button>
                        </div>
                        <table className="w-full text-[13.5px]">
                            <thead className="bg-muted text-muted-foreground text-left text-xs font-semibold tracking-[0.04em] uppercase">
                                <tr>
                                    <th className="px-4 py-[11px]">Case</th>
                                    <th className="px-4 py-[11px]">Status</th>
                                    <th className="px-4 py-[11px]">Priority</th>
                                    <th className="px-4 py-[11px]">Owner</th>
                                    <th className="px-4 py-[11px]">Issue</th>
                                </tr>
                            </thead>
                            <tbody>
                                {overview.needs_attention.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="text-muted-foreground px-4 py-8 text-center text-sm"
                                        >
                                            Nothing failing or blocked right
                                            now.
                                        </td>
                                    </tr>
                                ) : (
                                    overview.needs_attention.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="border-border hover:bg-muted border-t"
                                        >
                                            <td className="px-4 py-[13px]">
                                                <div className="font-semibold">
                                                    {item.name}
                                                </div>
                                                <div className="text-muted-foreground font-mono text-xs">
                                                    {item.external_id}
                                                </div>
                                            </td>
                                            <td className="px-4 py-[13px]">
                                                <StatusPill
                                                    status={item.status}
                                                />
                                            </td>
                                            <td className="px-4 py-[13px]">
                                                <PriorityMark
                                                    priority={
                                                        item.priority ??
                                                        'medium'
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-[13px]">
                                                {item.tester ? (
                                                    <UserAvatar
                                                        name={item.tester}
                                                    />
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-4 py-[13px]">
                                                {item.issue ? (
                                                    item.issue_url ? (
                                                        <a
                                                            href={
                                                                item.issue_url
                                                            }
                                                            className="bg-destructive-bg text-destructive inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11.5px] font-semibold"
                                                        >
                                                            <MockIcon
                                                                name="bug"
                                                                className="size-3"
                                                            />
                                                            {item.issue}
                                                        </a>
                                                    ) : (
                                                        <Tag tone="danger">
                                                            <MockIcon
                                                                name="bug"
                                                                className="size-3"
                                                            />
                                                            {item.issue}
                                                        </Tag>
                                                    )
                                                ) : item.issue_note ? (
                                                    <span className="text-muted-foreground">
                                                        — {item.issue_note}
                                                    </span>
                                                ) : (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={executeShow({
                                                                testPlan:
                                                                    item.plan_id,
                                                                testPlanItem:
                                                                    item.item_id,
                                                            })}
                                                        >
                                                            <MockIcon name="plus" />
                                                            Log issue
                                                        </Link>
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="bg-card overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                        <div className="flex items-center gap-2.5 border-b px-[18px] py-4">
                            <h3 className="text-[15px] font-bold">
                                Recent activity
                            </h3>
                        </div>
                        <ul className="space-y-4 p-[18px]">
                            {overview.activity.length === 0 ? (
                                <li className="text-muted-foreground text-sm">
                                    No recorded activity yet.
                                </li>
                            ) : (
                                overview.activity.map((event) => (
                                    <li
                                        key={event.id}
                                        className="flex items-start gap-[11px]"
                                    >
                                        <UserAvatar
                                            name={event.actor ?? 'System'}
                                        />
                                        <div>
                                            <p className="text-[13.5px]">
                                                <strong>
                                                    {event.is_viewer
                                                        ? 'You'
                                                        : (event.actor ??
                                                          'System')}
                                                </strong>{' '}
                                                {event.verb}
                                                {event.token ? (
                                                    <>
                                                        {' '}
                                                        <ActivityToken
                                                            style={
                                                                event.token_style
                                                            }
                                                        >
                                                            {event.token}
                                                        </ActivityToken>
                                                    </>
                                                ) : null}
                                                {event.tail
                                                    ? ` ${event.tail}`
                                                    : null}
                                            </p>
                                            <p className="text-text-subtle text-xs">
                                                {event.when ?? ''}
                                            </p>
                                        </div>
                                    </li>
                                ))
                            )}
                        </ul>
                    </div>
                </div>
            </div>
        </>
    );
}
