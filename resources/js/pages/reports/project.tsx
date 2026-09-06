import { Head, Link, setLayoutProps } from '@inertiajs/react';
import Heading from '@/components/heading';
import StatusBars from '@/components/reports/status-bars';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { plan as planReports, project as projectReports } from '@/routes/reports';
import { index as projectIndex } from '@/routes/projects';
import type { PlanDashboardRow, ReportProject } from '@/types/reports';

type ProjectReportsProps = {
    project: ReportProject;
    can: { dashboard: boolean };
    plans: PlanDashboardRow[];
};

export default function ProjectReports({
    project,
    can,
    plans,
}: ProjectReportsProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Reports', href: projectReports(project.id) },
        ],
    });

    return (
        <>
            <Head title={`${project.name} reports`} />

            <div className="space-y-6 p-4">
                <Heading
                    title="Reports"
                    description={
                        can.dashboard
                            ? `Latest-build status for every plan in ${project.name}.`
                            : `Plans in ${project.name} you can open reports for.`
                    }
                />

                {plans.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No plans yet.
                    </p>
                ) : (
                    <ul className="grid gap-4">
                        {plans.map((plan) => (
                            <li key={plan.id}>
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            <Link
                                                href={planReports(plan.id)}
                                                className="underline-offset-4 hover:underline"
                                            >
                                                {plan.name}
                                            </Link>
                                        </CardTitle>
                                    </CardHeader>

                                    <CardContent className="space-y-3">
                                        <p className="text-muted-foreground text-sm">
                                            {plan.items}{' '}
                                            {plan.items === 1
                                                ? 'item'
                                                : 'items'}
                                            {plan.build
                                                ? ` · ${plan.build.name}`
                                                : ''}
                                        </p>

                                        {can.dashboard && plan.counts && (
                                            <StatusBars
                                                counts={plan.counts}
                                                total={plan.items}
                                            />
                                        )}
                                    </CardContent>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
