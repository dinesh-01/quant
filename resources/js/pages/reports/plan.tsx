import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import PlanReportsController from '@/actions/App/Http/Controllers/Reports/PlanReportsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import StatusBars from '@/components/reports/status-bars';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show as planShow } from '@/routes/plans';
import {
    plan as planReports,
    planStatus as planStatusCsv,
    planStatusXlsx,
    project as projectReports,
} from '@/routes/reports';
import { index as projectIndex } from '@/routes/projects';
import type {
    BaselineComparison,
    CoveredRequirement,
    MilestoneProgressRow,
    PlanStatusReport,
    ReportBaselineSummary,
    ReportProject,
    TesterProgressRow,
    TimelineDay,
    UncoveredRequirement,
} from '@/types/reports';

type PlanReportsProps = {
    project: ReportProject;
    plan: { id: number; name: string };
    builds: { id: number; name: string }[];
    selectedBuildId: number | null;
    status: PlanStatusReport;
    timeline: TimelineDay[];
    baselines: ReportBaselineSummary[];
    comparison: BaselineComparison | null;
    testers: TesterProgressRow[];
    milestones: MilestoneProgressRow[];
    coverage: {
        covered: CoveredRequirement[];
        uncovered: UncoveredRequirement[];
    };
};

const statusLabels: Record<string, string> = {
    passed: 'Passed',
    failed: 'Failed',
    blocked: 'Blocked',
    not_run: 'Not run',
};

export default function PlanReports({
    project,
    plan,
    builds,
    selectedBuildId,
    status,
    timeline,
    baselines,
    comparison,
    testers,
    milestones,
    coverage,
}: PlanReportsProps) {
    const buildQuery =
        selectedBuildId === null ? {} : { query: { build: selectedBuildId } };

    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Reports', href: projectReports(project.id) },
            { title: plan.name, href: planReports(plan.id, buildQuery) },
        ],
    });

    return (
        <>
            <Head title={`${plan.name} reports`} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={`${plan.name} reports`}
                        description={`Latest completed run on the selected build. Drafts do not count.`}
                    />

                    <Button asChild size="sm" variant="ghost">
                        <Link href={planShow(plan.id)}>Plan</Link>
                    </Button>
                </div>

                {builds.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {builds.map((build) => (
                            <Button
                                key={build.id}
                                asChild
                                size="sm"
                                variant={
                                    build.id === selectedBuildId
                                        ? 'default'
                                        : 'outline'
                                }
                            >
                                <Link
                                    href={planReports(plan.id, {
                                        query: { build: build.id },
                                    })}
                                >
                                    {build.name}
                                </Link>
                            </Button>
                        ))}
                    </div>
                )}

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4">
                        <CardTitle>Status</CardTitle>

                        {selectedBuildId !== null && (
                            <div className="flex flex-wrap gap-3">
                                <a
                                    href={planStatusCsv.url(plan.id, {
                                        query: { build: selectedBuildId },
                                    })}
                                    className="text-sm underline-offset-4 hover:underline"
                                >
                                    Download CSV
                                </a>
                                <a
                                    href={planStatusXlsx.url(plan.id, {
                                        query: { build: selectedBuildId },
                                    })}
                                    className="text-sm underline-offset-4 hover:underline"
                                >
                                    Download spreadsheet
                                </a>
                            </div>
                        )}
                    </CardHeader>

                    <CardContent>
                        {status.total === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                This plan has no linked cases.
                            </p>
                        ) : (
                            <StatusBars
                                counts={status.counts}
                                total={status.total}
                            />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Status over time</CardTitle>
                    </CardHeader>

                    <CardContent>
                        {timeline.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No completed runs on this build yet.
                            </p>
                        ) : (
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="text-muted-foreground border-b">
                                        <th className="py-2 font-medium">
                                            Date
                                        </th>
                                        <th className="py-2 font-medium">
                                            Passed
                                        </th>
                                        <th className="py-2 font-medium">
                                            Failed
                                        </th>
                                        <th className="py-2 font-medium">
                                            Blocked
                                        </th>
                                        <th className="py-2 font-medium">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {timeline.map((day) => (
                                        <tr
                                            key={day.date}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-2">{day.date}</td>
                                            <td className="py-2">
                                                {day.passed}
                                            </td>
                                            <td className="py-2">
                                                {day.failed}
                                            </td>
                                            <td className="py-2">
                                                {day.blocked}
                                            </td>
                                            <td className="py-2">
                                                {day.total}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Baselines</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        <Form
                            {...PlanReportsController.storeBaseline.form(
                                plan.id,
                            )}
                            options={{ preserveScroll: true }}
                            className="flex flex-wrap items-end gap-3"
                        >
                            {({ errors }) => (
                                <>
                                    {selectedBuildId !== null && (
                                        <input
                                            type="hidden"
                                            name="build"
                                            value={selectedBuildId}
                                        />
                                    )}

                                    <div className="grid gap-2">
                                        <Label htmlFor="baseline_name">
                                            Name
                                        </Label>
                                        <Input
                                            id="baseline_name"
                                            name="name"
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <Button size="sm" variant="secondary">
                                        Save baseline
                                    </Button>
                                </>
                            )}
                        </Form>

                        {baselines.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No baselines saved for this plan.
                            </p>
                        ) : (
                            <ul className="space-y-2">
                                {baselines.map((baseline) => (
                                    <li
                                        key={baseline.id}
                                        className="flex flex-wrap items-center justify-between gap-3 text-sm"
                                    >
                                        <span>
                                            {baseline.name}
                                            {baseline.build_name !== null && (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    · {baseline.build_name}
                                                </span>
                                            )}
                                        </span>

                                        <Link
                                            href={planReports(plan.id, {
                                                query: {
                                                    ...(selectedBuildId ===
                                                    null
                                                        ? {}
                                                        : {
                                                              build: selectedBuildId,
                                                          }),
                                                    baseline: baseline.id,
                                                },
                                            })}
                                            className="underline-offset-4 hover:underline"
                                        >
                                            Compare to live
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {comparison !== null && (
                            <div className="space-y-3">
                                <div className="flex items-center justify-between gap-3">
                                    <p className="text-sm font-medium">
                                        Live vs {comparison.baseline.name}
                                    </p>

                                    <Button asChild size="sm" variant="ghost">
                                        <Link
                                            href={planReports(
                                                plan.id,
                                                buildQuery,
                                            )}
                                        >
                                            Clear
                                        </Link>
                                    </Button>
                                </div>

                                <ul className="grid gap-2 sm:grid-cols-4">
                                    {(
                                        [
                                            'passed',
                                            'failed',
                                            'blocked',
                                            'not_run',
                                        ] as const
                                    ).map((key) => (
                                        <li key={key} className="text-sm">
                                            <span className="text-muted-foreground capitalize">
                                                {key.replace('_', ' ')}
                                            </span>
                                            <span className="ml-2 font-medium">
                                                {
                                                    comparison.live_counts[
                                                        key
                                                    ]
                                                }{' '}
                                                /{' '}
                                                {
                                                    comparison.baseline_counts[
                                                        key
                                                    ]
                                                }
                                            </span>
                                        </li>
                                    ))}
                                </ul>

                                {comparison.changed.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">
                                        Every item matches this baseline.
                                    </p>
                                ) : (
                                    <table className="w-full text-left text-sm">
                                        <thead>
                                            <tr className="text-muted-foreground border-b">
                                                <th className="py-2 font-medium">
                                                    Case
                                                </th>
                                                <th className="py-2 font-medium">
                                                    Live
                                                </th>
                                                <th className="py-2 font-medium">
                                                    Baseline
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {comparison.changed.map(
                                                (item) => (
                                                    <tr
                                                        key={`${item.full_external_id}-${item.platform ?? ''}`}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="py-2">
                                                            {
                                                                item.full_external_id
                                                            }{' '}
                                                            {item.name}
                                                            {item.platform !==
                                                                null && (
                                                                <span className="text-muted-foreground">
                                                                    {' '}
                                                                    ·{' '}
                                                                    {
                                                                        item.platform
                                                                    }
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="py-2">
                                                            {item.live ??
                                                                '—'}
                                                        </td>
                                                        <td className="py-2">
                                                            {item.baseline ??
                                                                '—'}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Testers</CardTitle>
                    </CardHeader>

                    <CardContent>
                        {testers.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No testers are assigned on this build.
                            </p>
                        ) : (
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="text-muted-foreground border-b">
                                        <th className="py-2 font-medium">
                                            Tester
                                        </th>
                                        <th className="py-2 font-medium">
                                            Assigned
                                        </th>
                                        <th className="py-2 font-medium">
                                            Passed
                                        </th>
                                        <th className="py-2 font-medium">
                                            Failed
                                        </th>
                                        <th className="py-2 font-medium">
                                            Blocked
                                        </th>
                                        <th className="py-2 font-medium">
                                            Not run
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {testers.map((tester) => (
                                        <tr
                                            key={tester.user_id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-2">
                                                {tester.name}
                                            </td>
                                            <td className="py-2">
                                                {tester.assigned}
                                            </td>
                                            <td className="py-2">
                                                {tester.passed}
                                            </td>
                                            <td className="py-2">
                                                {tester.failed}
                                            </td>
                                            <td className="py-2">
                                                {tester.blocked}
                                            </td>
                                            <td className="py-2">
                                                {tester.not_run}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Milestones</CardTitle>
                    </CardHeader>

                    <CardContent>
                        {milestones.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                This plan has no milestones.
                            </p>
                        ) : (
                            <ul className="space-y-4">
                                {milestones.map((milestone) => (
                                    <li key={milestone.id} className="space-y-2">
                                        <p className="font-medium">
                                            {milestone.name}{' '}
                                            <span className="text-muted-foreground font-normal">
                                                {milestone.target_date}
                                            </span>
                                        </p>

                                        <ul className="grid gap-2 sm:grid-cols-3">
                                            {(
                                                [
                                                    'high',
                                                    'medium',
                                                    'low',
                                                ] as const
                                            ).map((band) => (
                                                <li
                                                    key={band}
                                                    className="text-sm"
                                                >
                                                    <span className="capitalize">
                                                        {band}
                                                    </span>
                                                    :{' '}
                                                    {
                                                        milestone.bands[band]
                                                            .actual_percent
                                                    }
                                                    % of{' '}
                                                    {
                                                        milestone.bands[band]
                                                            .target_percent
                                                    }
                                                    % (
                                                    {
                                                        milestone.bands[band]
                                                            .passed
                                                    }
                                                    /
                                                    {
                                                        milestone.bands[band]
                                                            .items
                                                    }
                                                    )
                                                </li>
                                            ))}
                                        </ul>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Requirement coverage</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        {coverage.covered.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No requirements are covered by cases on this
                                plan.
                            </p>
                        ) : (
                            <ul className="space-y-2">
                                {coverage.covered.map((requirement) => (
                                    <li
                                        key={requirement.id}
                                        className="flex justify-between gap-4 text-sm"
                                    >
                                        <span>
                                            {requirement.doc_id}{' '}
                                            {requirement.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {requirement.passed_items}/
                                            {requirement.covering_items}{' '}
                                            {
                                                statusLabels[
                                                    requirement.status
                                                ]
                                            }
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {coverage.uncovered.length > 0 && (
                            <div className="space-y-2">
                                <p className="text-sm font-medium">
                                    Not on this plan
                                </p>
                                <ul className="text-muted-foreground space-y-1 text-sm">
                                    {coverage.uncovered.map((requirement) => (
                                        <li key={requirement.id}>
                                            {requirement.doc_id}{' '}
                                            {requirement.name}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
