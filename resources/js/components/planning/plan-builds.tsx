import { Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { create, edit } from '@/routes/builds';
import type { PlanBuildSummary, PlanContentsAbilities } from '@/types/test-plan';

export default function PlanBuilds({
    planId,
    builds,
    can,
}: {
    planId: number;
    builds: PlanBuildSummary[];
    can: PlanContentsAbilities;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-4">
                <CardTitle>Builds</CardTitle>

                {can.manageBuilds && (
                    <Button asChild size="sm">
                        <Link href={create(planId)}>
                            <Plus className="size-4" />
                            New build
                        </Link>
                    </Button>
                )}
            </CardHeader>

            <CardContent>
                {builds.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No builds yet. A build is a named snapshot of the
                        software this plan is executed against.
                    </p>
                ) : (
                    <ul className="divide-y rounded-md border">
                        {builds.map((build) => (
                            <li
                                key={build.id}
                                className="flex items-start justify-between gap-4 p-3"
                            >
                                <div className="min-w-0 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="font-medium">
                                            {build.name}
                                        </p>

                                        {!build.is_open && (
                                            <Badge variant="secondary">
                                                Closed
                                            </Badge>
                                        )}
                                        {!build.is_active && (
                                            <Badge variant="secondary">
                                                Inactive
                                            </Badge>
                                        )}
                                    </div>

                                    <p className="text-muted-foreground text-sm">
                                        {build.release_date
                                            ? `Release ${build.release_date}`
                                            : 'No release date.'}
                                        {build.notes ? ` ${build.notes}` : ''}
                                    </p>
                                </div>

                                {can.manageBuilds && (
                                    <Button asChild size="sm" variant="ghost">
                                        <Link href={edit(build.id)}>
                                            <Pencil className="size-4" />
                                            Edit
                                        </Link>
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
