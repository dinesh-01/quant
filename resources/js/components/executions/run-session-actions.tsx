import { Link } from '@inertiajs/react';
import { MockIcon } from '@/components/chrome/mock-icon';
import { StatusPill } from '@/components/chrome/stat-card';
import { Button } from '@/components/ui/button';
import { index as selectorIndex } from '@/routes/plan-selector';

export function RunSessionActions({
    projectId,
    buildName,
}: {
    projectId: number;
    buildName?: string | null;
}) {
    return (
        <>
            {buildName ? (
                <StatusPill
                    status="running"
                    label={`${buildName} · live run`}
                />
            ) : null}
            <Button size="sm" variant="outline" asChild>
                <Link href={selectorIndex(projectId)}>
                    <MockIcon name="check" />
                    Finish run
                </Link>
            </Button>
        </>
    );
}
