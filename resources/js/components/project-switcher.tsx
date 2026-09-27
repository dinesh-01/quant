import { Link, router, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { MockIcon } from '@/components/chrome/mock-icon';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useNavProject } from '@/hooks/use-nav-project';
import { rememberLastProject } from '@/lib/last-project';
import { show as projectOverview } from '@/routes/projects';
import { cn } from '@/lib/utils';

function pathForProject(currentPath: string, fromId: number, toId: number): string {
    const token = `/projects/${fromId}`;

    if (currentPath === token || currentPath.startsWith(`${token}/`)) {
        return currentPath.replace(token, `/projects/${toId}`);
    }

    return projectOverview.url(toId);
}

export function ProjectSwitcher() {
    const { url } = usePage();
    const { project, currentProject, availableProjects } = useNavProject();

    useEffect(() => {
        if (currentProject) {
            rememberLastProject(currentProject.id);
        }
    }, [currentProject]);

    if (!project) {
        return (
            <span className="text-muted-foreground text-sm">No project</span>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="border-border bg-card h-auto gap-[9px] rounded-lg px-3 py-1.5 text-[13px] font-semibold"
                >
                    <span className="bg-primary size-[9px] rounded-full" />
                    <span className="max-w-40 truncate">{project.name}</span>
                    <span className="text-text-subtle font-medium">
                        {project.prefix}
                    </span>
                    <MockIcon
                        name="chev-down"
                        className="text-text-subtle size-4"
                    />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="min-w-56">
                {availableProjects.map((item) => (
                    <DropdownMenuItem
                        key={item.id}
                        className="gap-2"
                        onClick={() => {
                            rememberLastProject(item.id);
                            router.visit(
                                pathForProject(
                                    new URL(url, 'http://localhost').pathname,
                                    project.id,
                                    item.id,
                                ),
                            );
                        }}
                    >
                        <Check
                            className={cn(
                                'size-3.5',
                                item.id === project.id
                                    ? 'opacity-100'
                                    : 'opacity-0',
                            )}
                        />
                        <span className="flex-1 truncate">{item.name}</span>
                        <span className="text-muted-foreground font-mono text-[11px]">
                            {item.prefix}
                        </span>
                    </DropdownMenuItem>
                ))}
                {availableProjects.length === 0 && (
                    <DropdownMenuItem asChild>
                        <Link href={projectOverview.url(project.id)}>
                            {project.name}
                        </Link>
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
