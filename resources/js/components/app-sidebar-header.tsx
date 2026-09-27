import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { MockIcon } from '@/components/chrome/mock-icon';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { ProjectSwitcher } from '@/components/project-switcher';
import CaseSearch from '@/components/test-specification/case-search';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useNavProject } from '@/hooks/use-nav-project';
import { create as createCase } from '@/routes/test-cases';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
    headerActions,
}: {
    breadcrumbs?: BreadcrumbItemType[];
    headerActions?: ReactNode;
}) {
    const { project, currentProject } = useNavProject();
    const canSearch = Boolean(currentProject?.can.viewSpecification);

    return (
        <header className="bg-card/85 sticky top-0 z-20 flex h-[62px] shrink-0 items-center gap-3 border-b px-4 backdrop-blur md:px-6">
            <SidebarTrigger className="-ml-1 md:hidden" />
            <ProjectSwitcher />
            <Breadcrumbs breadcrumbs={breadcrumbs} />
            {canSearch && currentProject && (
                <div className="ml-1 hidden max-w-[420px] flex-1 lg:block">
                    <CaseSearch
                        project={currentProject}
                        variant="header"
                        placeholder="Search cases…"
                    />
                </div>
            )}
            <div className="ml-auto flex items-center gap-2">
                {headerActions}
                {project && currentProject?.can.viewSpecification && (
                    <Button size="sm" className="hidden sm:inline-flex" asChild>
                        <Link href={createCase.url(project.id)}>
                            <MockIcon name="plus" />
                            New case
                        </Link>
                    </Button>
                )}
                <Button
                    variant="outline"
                    size="icon"
                    className="relative size-9"
                    type="button"
                    aria-label="Notifications"
                >
                    <MockIcon name="bell" className="size-[18px]" />
                    <span className="bg-destructive absolute -top-[3px] -right-[3px] size-2 rounded-full border-2 border-white" />
                </Button>
            </div>
        </header>
    );
}
