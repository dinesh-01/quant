import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ListTree, UserPlus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as executeIndex } from '@/routes/executions';
import { index as selectorIndex } from '@/routes/plan-selector';
import { show } from '@/routes/plans';
import { index as projectIndex } from '@/routes/projects';
import type { PlanProject, SelectablePlan } from '@/types/test-plan';

type PlanSelectorProps = {
    project: PlanProject;
    plans: SelectablePlan[];
};

/**
 * Plans this person can run or inspect, including a private plan they hold a
 * role for. The management list stays on create_test_plans.
 */
export default function PlanSelectorIndex({
    project,
    plans,
}: PlanSelectorProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Execute', href: selectorIndex(project.id) },
        ],
    });

    return (
        <>
            <Head title={`Execute ${project.name}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title="Execute"
                    description={`Plans in ${project.name} you can run or inspect.`}
                />

                {plans.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-10 text-center">
                        <p className="text-muted-foreground text-sm">
                            No plans are available to you yet. Ask a leader to
                            give you a role on a plan, or to add you to the
                            project.
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {plans.map((plan) => (
                            <li
                                key={plan.id}
                                className="flex items-start justify-between gap-4 p-4"
                            >
                                <div className="min-w-0 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="font-medium">
                                            {plan.name}
                                        </h2>

                                        {!plan.is_open && (
                                            <Badge variant="secondary">
                                                Closed
                                            </Badge>
                                        )}
                                        {!plan.is_public && (
                                            <Badge variant="outline">
                                                Private
                                            </Badge>
                                        )}
                                        {plan.can_execute && (
                                            <Badge>Can run</Badge>
                                        )}
                                    </div>

                                    <p className="text-muted-foreground text-sm">
                                        {plan.description ??
                                            'No description.'}
                                    </p>
                                </div>

                                <div className="flex shrink-0 gap-1">
                                    <Button asChild size="sm">
                                        <Link href={executeIndex(plan.id)}>
                                            <ListTree className="size-4" />
                                            Open
                                        </Link>
                                    </Button>

                                    {plan.can_assign && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <Link
                                                href={show.url(plan.id, {
                                                    query: { tab: 'assign' },
                                                })}
                                            >
                                                <UserPlus className="size-4" />
                                                Assign
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
