import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ListTree, Pencil, Plus, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index, show } from '@/routes/plans';
import { index as memberIndex } from '@/routes/plans/members';
import { index as projectIndex } from '@/routes/projects';
import type { PlanProject, PlanSummary } from '@/types/test-plan';

type PlanIndexProps = {
    project: PlanProject;
    plans: PlanSummary[];
};

export default function TestPlanIndex({ project, plans }: PlanIndexProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Test plans', href: index(project.id) },
        ],
    });

    return (
        <>
            <Head title={`${project.name} test plans`} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Test plans"
                        description={`Rounds of testing scheduled against ${project.name}.`}
                    />

                    <Button asChild>
                        <Link href={create(project.id)}>
                            <Plus className="size-4" />
                            New plan
                        </Link>
                    </Button>
                </div>

                {plans.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-10 text-center">
                        <p className="text-muted-foreground text-sm">
                            No test plans yet. A plan is a selection of this
                            project's test cases scheduled for execution.
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
                                        {!plan.is_active && (
                                            <Badge variant="secondary">
                                                Inactive
                                            </Badge>
                                        )}
                                        {!plan.is_public && (
                                            <Badge variant="outline">
                                                Private
                                            </Badge>
                                        )}
                                    </div>

                                    <p className="text-muted-foreground text-sm">
                                        {plan.description ?? 'No description.'}
                                    </p>
                                </div>

                                <div className="flex shrink-0 gap-1">
                                    <Button asChild size="sm">
                                        <Link href={show(plan.id)}>
                                            <ListTree className="size-4" />
                                            Contents
                                        </Link>
                                    </Button>

                                    <Button asChild size="sm" variant="ghost">
                                        <Link href={memberIndex(plan.id)}>
                                            <Users className="size-4" />
                                            Members
                                        </Link>
                                    </Button>

                                    <Button asChild size="sm" variant="ghost">
                                        <Link href={edit(plan.id)}>
                                            <Pencil className="size-4" />
                                            Edit
                                        </Link>
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
