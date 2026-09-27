import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import { Check, FileText, Pencil, Play, X } from 'lucide-react';
import { PageHead } from '@/components/chrome/page-head';
import {
    ProgressMeter,
    SegControl,
    StatCard,
} from '@/components/chrome/stat-card';
import { index as customReports } from '@/routes/custom-reports';
import { plan as planReports, project as projectReports } from '@/routes/reports';
import type { PlanDashboardRow, ReportProject } from '@/types/reports';

type ProjectReportsProps = {
    project: ReportProject;
    can: { dashboard: boolean };
    plans: PlanDashboardRow[];
    case_stats: {
        total: number;
        automated: number;
        manual: number;
    };
};

export default function ProjectReports({
    project,
    can,
    plans,
    case_stats = { total: 0, automated: 0, manual: 0 },
}: ProjectReportsProps) {
    const [view, setView] = useState<'cases' | 'execution'>('cases');
    const automatedPct =
        case_stats.total === 0
            ? 0
            : Math.round((case_stats.automated / case_stats.total) * 100);
    const manualPct =
        case_stats.total === 0
            ? 0
            : Math.round((case_stats.manual / case_stats.total) * 100);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Standard Reports', href: projectReports(project.id) },
        ],
    });

    return (
        <>
            <Head title={`${project.name} reports`} />

            <div className="space-y-5 p-6">
                <PageHead
                    title="Reports"
                    description="Automation coverage snapshot and latest-build status for every plan."
                    actions={
                        <SegControl
                            items={[
                                {
                                    label: 'Test Cases',
                                    active: view === 'cases',
                                    onClick: () => setView('cases'),
                                },
                                {
                                    label: 'Execution',
                                    active: view === 'execution',
                                    onClick: () => setView('execution'),
                                },
                            ]}
                        />
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <span className="bg-card rounded-md px-3 py-1 text-sm font-medium shadow-xs">
                        Standard
                    </span>
                    <Link
                        href={customReports(project.id)}
                        className="text-muted-foreground px-3 py-1 text-sm"
                    >
                        Custom
                    </Link>
                </div>

                {view === 'cases' ? (
                    <div className="grid gap-[18px] lg:grid-cols-[236px_1fr]">
                        <aside className="bg-card self-start rounded-xl border p-4 shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                            <div className="mb-3.5 flex items-center justify-between">
                                <span className="text-text-subtle text-[11px] font-bold tracking-[0.06em] uppercase">
                                    Filters
                                </span>
                            </div>
                            <p className="text-muted-foreground text-[12.5px]">
                                Showing every suite in {project.name}. Severity
                                and keyword filters will apply here once they
                                are saved on the report.
                            </p>
                        </aside>
                        <div className="space-y-4">
                            <p className="text-text-subtle text-[11px] font-bold tracking-[0.07em] uppercase">
                                Total test cases · {project.name}
                            </p>
                            <div className="grid gap-[18px] sm:grid-cols-2 xl:grid-cols-5">
                                <StatCard
                                    label="Total test cases"
                                    value={case_stats.total}
                                    hint="100% of project"
                                    icon={<FileText className="size-4" />}
                                    tone="info"
                                />
                                <StatCard
                                    label="Automated"
                                    value={case_stats.automated}
                                    hint={`${automatedPct}% of total`}
                                    icon={<Check className="size-4" />}
                                    tone="success"
                                />
                                <StatCard
                                    label="Manual"
                                    value={case_stats.manual}
                                    hint={`${manualPct}% of total`}
                                    icon={<Pencil className="size-4" />}
                                />
                                <StatCard
                                    label="In progress"
                                    value={0}
                                    hint="0% of total"
                                    icon={<Play className="size-4" />}
                                    tone="warning"
                                />
                                <StatCard
                                    label="Not automated"
                                    value={case_stats.manual}
                                    hint={`${manualPct}% of total`}
                                    icon={<X className="size-4" />}
                                    tone="neutral"
                                />
                            </div>
                            <div className="grid gap-[18px] md:grid-cols-2">
                                <div className="bg-card rounded-xl border p-[18px] shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                                    <p className="text-text-subtle text-[11px] font-bold tracking-[0.06em] uppercase">
                                        Integration coverage
                                    </p>
                                    <p className="text-primary-700 mt-1 text-[26px] font-extrabold tracking-[-0.02em]">
                                        {automatedPct}%
                                    </p>
                                    <p className="text-muted-foreground text-[12.5px]">
                                        automated ÷ cases
                                    </p>
                                </div>
                                <div className="bg-card rounded-xl border p-[18px] shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                                    <p className="text-text-subtle text-[11px] font-bold tracking-[0.06em] uppercase">
                                        Linked scripts
                                    </p>
                                    <p className="mt-1 text-[26px] font-extrabold tracking-[-0.02em]">
                                        {case_stats.automated}
                                    </p>
                                    <p className="text-muted-foreground text-[12.5px]">
                                        of {case_stats.total} cases
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                ) : plans.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No plans yet.
                    </p>
                ) : (
                    <ul className="grid gap-[18px]">
                        {plans.map((plan) => (
                            <li
                                key={plan.id}
                                className="bg-card rounded-xl border p-[18px] shadow-[0_1px_2px_rgba(16,24,40,.06)]"
                            >
                                <div className="mb-3 flex items-center justify-between gap-3">
                                    <Link
                                        href={planReports(plan.id)}
                                        className="text-[15px] font-bold hover:underline"
                                    >
                                        {plan.name}
                                    </Link>
                                    <span className="text-muted-foreground text-[12.5px]">
                                        {plan.items}{' '}
                                        {plan.items === 1 ? 'item' : 'items'}
                                        {plan.build
                                            ? ` · ${plan.build.name}`
                                            : ''}
                                    </span>
                                </div>
                                {can.dashboard && plan.counts && (
                                    <ProgressMeter
                                        counts={plan.counts}
                                        total={plan.items}
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
