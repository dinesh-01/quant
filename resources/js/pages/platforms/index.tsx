import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Monitor, Pencil, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/platforms';
import { index as projectIndex } from '@/routes/projects';
import type { PlatformProject, PlatformSummary } from '@/types/platform';

type PlatformIndexProps = {
    project: PlatformProject;
    platforms: PlatformSummary[];
    can: { manage: boolean };
};

/**
 * The project's platform vocabulary.
 *
 * Curating the list and assigning it to a plan or a version are separate
 * rights, so this screen is read-only for someone who may only view.
 */
export default function PlatformIndex({
    project,
    platforms,
    can,
}: PlatformIndexProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Platforms', href: index(project.id) },
        ],
    });

    return (
        <>
            <Head title={`${project.name} platforms`} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Platforms"
                        description={`The environments ${project.name} designs and executes against.`}
                    />

                    {can.manage && (
                        <Button asChild>
                            <Link href={create(project.id)}>
                                <Plus className="size-4" />
                                New platform
                            </Link>
                        </Button>
                    )}
                </div>

                {platforms.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-10 text-center">
                        <p className="text-muted-foreground text-sm">
                            No platforms yet. They name environments —{' '}
                            <span className="font-medium">Chrome</span>,{' '}
                            <span className="font-medium">iOS</span>,{' '}
                            <span className="font-medium">API</span> — that a
                            plan can run against and a version can be written
                            for.
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {platforms.map((platform) => (
                            <li
                                key={platform.id}
                                className="flex items-start justify-between gap-4 p-4"
                            >
                                <div className="min-w-0 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="flex items-center gap-1.5 font-medium">
                                            <Monitor className="text-muted-foreground size-3.5" />
                                            {platform.name}
                                        </h2>

                                        {!platform.is_open && (
                                            <Badge variant="secondary">
                                                Closed
                                            </Badge>
                                        )}
                                        {!platform.enable_on_design && (
                                            <Badge variant="outline">
                                                Execution only
                                            </Badge>
                                        )}
                                        {!platform.enable_on_execution && (
                                            <Badge variant="outline">
                                                Design only
                                            </Badge>
                                        )}
                                        {platform.plan_items_count > 0 && (
                                            <Badge variant="secondary">
                                                {platform.plan_items_count}{' '}
                                                {platform.plan_items_count === 1
                                                    ? 'plan item'
                                                    : 'plan items'}
                                            </Badge>
                                        )}
                                    </div>

                                    <p className="text-muted-foreground text-sm">
                                        {platform.notes ?? 'No notes.'}
                                    </p>
                                </div>

                                {can.manage && (
                                    <Button asChild size="sm" variant="ghost">
                                        <Link href={edit(platform.id)}>
                                            <Pencil className="size-4" />
                                            Edit
                                        </Link>
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
