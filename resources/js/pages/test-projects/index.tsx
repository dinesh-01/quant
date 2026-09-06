import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { FolderOpen, Pencil, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/projects';
import { show } from '@/routes/specification';
import type { ProjectSummary } from '@/types/test-project';

type ProjectIndexProps = {
    projects: ProjectSummary[];
    can: { manage: boolean };
};

/**
 * The signed-in landing screen: every project this user can reach.
 *
 * Legacy held the current project in the session and offered it through a
 * dropdown on every page, which meant no project screen had a shareable URL.
 * Here the project is chosen once from this list and then carried in the URL.
 */
export default function TestProjectIndex({ projects, can }: ProjectIndexProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Test projects', href: index() }],
    });

    return (
        <>
            <Head title="Test projects" />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Test projects"
                        description="Choose a project to work in."
                    />

                    {can.manage && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                New project
                            </Link>
                        </Button>
                    )}
                </div>

                {projects.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-10 text-center">
                        <p className="text-muted-foreground text-sm">
                            {can.manage
                                ? 'No test projects yet. Create one to start writing test cases.'
                                : 'You have not been given access to any test project yet. Ask an administrator to assign you a role.'}
                        </p>
                    </div>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {projects.map((project) => (
                            <li
                                key={project.id}
                                className="flex flex-col gap-3 rounded-lg border p-4"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <h2 className="truncate font-medium">
                                            {project.name}
                                        </h2>
                                        <p className="text-muted-foreground mt-0.5 font-mono text-xs">
                                            {project.prefix}-1
                                        </p>
                                    </div>

                                    <div className="flex shrink-0 gap-1">
                                        {!project.is_active && (
                                            <Badge variant="secondary">
                                                Inactive
                                            </Badge>
                                        )}
                                        {!project.is_public && (
                                            <Badge variant="outline">
                                                Private
                                            </Badge>
                                        )}
                                    </div>
                                </div>

                                <p className="text-muted-foreground line-clamp-2 min-h-8 text-sm">
                                    {project.description ?? 'No description.'}
                                </p>

                                <p className="text-muted-foreground text-xs">
                                    {project.test_suites_count} suites ·{' '}
                                    {project.test_cases_count} cases ·{' '}
                                    {project.test_plans_count} plans
                                </p>

                                <div className="mt-auto flex gap-2 pt-1">
                                    {project.can_open && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="secondary"
                                        >
                                            <Link href={show(project.id)}>
                                                <FolderOpen className="size-4" />
                                                Specification
                                            </Link>
                                        </Button>
                                    )}

                                    {can.manage && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <Link href={edit(project.id)}>
                                                <Pencil className="size-4" />
                                                Edit
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
