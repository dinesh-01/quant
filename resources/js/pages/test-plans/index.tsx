import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Calendar, Play, Plus } from 'lucide-react';
import { PageHead } from '@/components/chrome/page-head';
import {
    AvatarStack,
    ProgressMeter,
    SegControl,
    StatusPill,
    Tag,
} from '@/components/chrome/stat-card';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/plans';
import { index as selectorIndex } from '@/routes/plan-selector';
import { plan as planReports } from '@/routes/reports';
import type { PlanProject, PlanSummary } from '@/types/test-plan';

type PlanIndexProps = {
    project: PlanProject;
    plans: PlanSummary[];
    filter: 'active' | 'draft' | 'archived';
    status_counts: {
        active: number;
        draft: number;
        archived: number;
    };
};

const runLabels: Record<string, string> = {
    in_progress: 'In progress',
    running: 'In progress',
    blocked: 'Blocked',
    completed: 'Completed',
    draft: 'Draft',
    archived: 'Archived',
};

export default function TestPlanIndex({
    project,
    plans,
    filter,
    status_counts,
}: PlanIndexProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Test Plans', href: index(project.id) }],
    });

    return (
        <>
            <Head title={`${project.name} test plans`} />

            <div className="flex-1 space-y-5 p-6">
                <PageHead
                    title="Test Plans"
                    description="Plans bundle cases, builds, platforms and milestones — then hand off to execution."
                    actions={
                        <Button asChild>
                            <Link href={create(project.id)}>
                                <Plus />
                                New plan
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-center gap-2.5">
                    <SegControl
                        items={[
                            {
                                href: index.url(project.id, {
                                    query: { status: 'active' },
                                }),
                                label: `Active ${status_counts.active}`,
                                active: filter === 'active',
                            },
                            {
                                href: index.url(project.id, {
                                    query: { status: 'draft' },
                                }),
                                label: `Draft ${status_counts.draft}`,
                                active: filter === 'draft',
                            },
                            {
                                href: index.url(project.id, {
                                    query: { status: 'archived' },
                                }),
                                label: `Archived ${status_counts.archived}`,
                                active: filter === 'archived',
                            },
                        ]}
                    />
                </div>

                {plans.length === 0 ? (
                    <div className="bg-card rounded-xl border border-dashed p-10 text-center">
                        <p className="text-muted-foreground text-sm">
                            No {filter} test plans yet.
                        </p>
                    </div>
                ) : (
                    <ul className="grid gap-[18px] md:grid-cols-2">
                        {plans.map((plan) => (
                            <li
                                key={plan.id}
                                className="bg-card overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)]"
                            >
                                <div className="flex items-start justify-between gap-3 border-b px-[18px] py-4">
                                    <div>
                                        <p className="text-muted-foreground font-mono text-xs">
                                            {project.prefix ?? 'P'}-{plan.id}
                                        </p>
                                        <h2 className="mt-0.5 text-[15px] font-bold">
                                            {plan.name}
                                        </h2>
                                    </div>
                                    <StatusPill
                                        status={plan.run_status ?? 'running'}
                                        label={
                                            runLabels[plan.run_status ?? ''] ??
                                            plan.run_status
                                        }
                                    />
                                </div>
                                <div className="space-y-3.5 p-[18px]">
                                    <div className="flex flex-wrap gap-2">
                                        {plan.build && (
                                            <Tag>Build {plan.build}</Tag>
                                        )}
                                        {plan.milestone && (
                                            <Tag tone="neutral">
                                                <Calendar className="size-3" />
                                                {plan.milestone}
                                            </Tag>
                                        )}
                                        {(plan.platforms ?? []).map((name) => (
                                            <Tag key={name} tone="info">
                                                {name}
                                            </Tag>
                                        ))}
                                    </div>
                                    {plan.counts && (
                                        <ProgressMeter
                                            counts={plan.counts}
                                            total={plan.items ?? 0}
                                        />
                                    )}
                                    <div className="flex items-center justify-between gap-3 text-[12.5px]">
                                        <div className="flex flex-wrap gap-4">
                                            <span>
                                                <strong>{plan.items ?? 0}</strong>{' '}
                                                cases
                                            </span>
                                            {plan.counts && (
                                                <>
                                                    <span className="text-success">
                                                        {plan.counts.passed}{' '}
                                                        passed
                                                    </span>
                                                    <span className="text-destructive">
                                                        {plan.counts.failed}{' '}
                                                        failed
                                                    </span>
                                                    {plan.counts.blocked > 0 && (
                                                        <span className="text-warning">
                                                            {plan.counts.blocked}{' '}
                                                            blocked
                                                        </span>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                        <AvatarStack
                                            names={plan.assignees ?? []}
                                        />
                                    </div>
                                    <div className="border-border flex items-center justify-between border-t pt-3.5">
                                        <p className="text-text-subtle text-xs">
                                            {plan.description
                                                ? plan.description.replace(
                                                      /<[^>]+>/g,
                                                      '',
                                                  )
                                                : 'No description.'}
                                        </p>
                                        {plan.is_open ? (
                                            <Button asChild size="sm">
                                                <Link
                                                    href={selectorIndex(
                                                        project.id,
                                                    )}
                                                >
                                                    <Play />
                                                    Resume run
                                                </Link>
                                            </Button>
                                        ) : (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link href={planReports(plan.id)}>
                                                    Report
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                    <Link
                                        href={show(plan.id)}
                                        className="text-primary-700 text-xs font-semibold"
                                    >
                                        Open plan
                                    </Link>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
