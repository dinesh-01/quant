import { Link, usePage } from '@inertiajs/react';
import {
    ClipboardList,
    BookOpen,
    Bug,
    ChartColumn,
    Code,
    FolderTree,
    LayoutGrid,
    ListChecks,
    ScrollText,
    ShieldCheck,
    SlidersHorizontal,
    Tags,
    Monitor,
    Users,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as customFieldIndex } from '@/routes/custom-fields';
import { index as eventIndex } from '@/routes/events';
import { index as keywordIndex } from '@/routes/keywords';
import { index as selectorIndex } from '@/routes/plan-selector';
import { index as planIndex } from '@/routes/plans';
import { index as platformIndex } from '@/routes/platforms';
import { project as projectReports } from '@/routes/reports';
import { show as codeTrackerShow } from '@/routes/code-trackers';
import { show as issueTrackerShow } from '@/routes/issue-trackers';
import { index as projectIndex } from '@/routes/projects';
import { index as roleIndex } from '@/routes/roles';
import { index as projectCustomFieldIndex } from '@/routes/projects/custom-fields';
import { index as memberIndex } from '@/routes/projects/members';
import { show as requirementsShow } from '@/routes/requirements';
import { show as specificationShow } from '@/routes/specification';
import { index as userIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Test projects',
        href: projectIndex(),
        icon: ListChecks,
    },
];

export function AppSidebar() {
    const { auth, currentProject } = usePage().props;

    /**
     * Administration is a separate group because these screens are not scoped
     * to a project — they are governed by system abilities, which only a global
     * role can grant.
     */
    const adminNavItems: NavItem[] = [
        ...(auth.can.manageUsers
            ? [{ title: 'Users', href: userIndex(), icon: UsersRound }]
            : []),
        ...(auth.can.manageRoles
            ? [{ title: 'Roles', href: roleIndex(), icon: ShieldCheck }]
            : []),
        /*
         * Under administration rather than under the project, because a
         * definition is application-wide: editing one reaches every project
         * that has it enabled. Which of them a single project uses is the
         * project-scoped entry below.
         */
        ...(auth.can.viewCustomFields
            ? [
                  {
                      title: 'Custom fields',
                      href: customFieldIndex(),
                      icon: SlidersHorizontal,
                  },
              ]
            : []),
        ...(auth.can.viewEventLog
            ? [{ title: 'Event log', href: eventIndex(), icon: ScrollText }]
            : []),
    ];

    /**
     * Shown only while a route is scoped to a project, because these
     * destinations need a project id to exist at all, and only for the
     * abilities the user actually holds there. More items join this group as
     * the project-scoped areas land.
     */
    const projectNavItems: NavItem[] = currentProject
        ? [
              ...(currentProject.can.viewSpecification
                  ? [
                        {
                            title: 'Specification',
                            href: specificationShow(currentProject.id),
                            icon: FolderTree,
                        },
                    ]
                  : []),
              ...(currentProject.can.viewRequirements
                  ? [
                        {
                            title: 'Requirements',
                            href: requirementsShow(currentProject.id),
                            icon: BookOpen,
                        },
                    ]
                  : []),
              /*
               * Next to the specification because that is what keywords tag,
               * and reachable on its own because the vocabulary is edited
               * before there is anything to tag with it.
               */
              ...(currentProject.can.viewKeywords
                  ? [
                        {
                            title: 'Keywords',
                            href: keywordIndex(currentProject.id),
                            icon: Tags,
                        },
                    ]
                  : []),
              ...(currentProject.can.viewPlatforms
                  ? [
                        {
                            title: 'Platforms',
                            href: platformIndex(currentProject.id),
                            icon: Monitor,
                        },
                    ]
                  : []),
              ...(currentProject.can.manageTestPlans
                  ? [
                        {
                            title: 'Test plans',
                            href: planIndex(currentProject.id),
                            icon: ClipboardList,
                        },
                    ]
                  : []),
              ...(currentProject.can.selectPlans
                  ? [
                        {
                            title: 'Execute',
                            href: selectorIndex(currentProject.id),
                            icon: ListChecks,
                        },
                    ]
                  : []),
              ...(currentProject.can.viewReports
                  ? [
                        {
                            title: 'Reports',
                            href: projectReports(currentProject.id),
                            icon: ChartColumn,
                        },
                    ]
                  : []),
              ...(currentProject.can.viewCodeTrackers
                  ? [
                        {
                            title: 'Code tracker',
                            href: codeTrackerShow(currentProject.id),
                            icon: Code,
                        },
                    ]
                  : []),
              ...(currentProject.can.viewIssueTrackers
                  ? [
                        {
                            title: 'Issue tracker',
                            href: issueTrackerShow(currentProject.id),
                            icon: Bug,
                        },
                    ]
                  : []),
              ...(currentProject.can.assignCustomFields
                  ? [
                        {
                            title: 'Custom fields',
                            href: projectCustomFieldIndex(currentProject.id),
                            icon: SlidersHorizontal,
                        },
                    ]
                  : []),
              ...(currentProject.can.manageMembers
                  ? [
                        {
                            title: 'Members',
                            href: memberIndex(currentProject.id),
                            icon: Users,
                        },
                    ]
                  : []),
          ]
        : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-4">
                <NavMain items={mainNavItems} />

                {currentProject && (
                    <NavMain
                        items={projectNavItems}
                        label={currentProject.name}
                    />
                )}

                {adminNavItems.length > 0 && (
                    <NavMain items={adminNavItems} label="Administration" />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
