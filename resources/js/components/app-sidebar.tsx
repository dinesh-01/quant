import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { NavIcons } from '@/components/chrome/mock-icon';
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
    SidebarRail,
} from '@/components/ui/sidebar';
import { useNavProject } from '@/hooks/use-nav-project';
import { index as coverageIndex } from '@/routes/code-coverage';
import { index as customReportsIndex } from '@/routes/custom-reports';
import { index as issuesIndex } from '@/routes/issues';
import { index as selectorIndex } from '@/routes/plan-selector';
import { index as planIndex } from '@/routes/plans';
import { show as projectOverview } from '@/routes/projects';
import { index as projectSettings } from '@/routes/project-settings';
import { project as projectReports } from '@/routes/reports';
import { index as roleIndex } from '@/routes/roles';
import { show as specificationShow } from '@/routes/specification';
import { index as eventIndex } from '@/routes/events';
import { index as userIndex } from '@/routes/users';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props;
    const { project, currentProject } = useNavProject();
    const can = currentProject?.can;

    const workspaceItems: NavItem[] = project
        ? [
              {
                  title: 'Overview',
                  href: projectOverview(project.id),
                  icon: NavIcons.dashboard,
              },
              ...(can?.viewSpecification || !currentProject
                  ? [
                        {
                            title: 'Test Suites',
                            href: specificationShow(project.id),
                            icon: NavIcons.folder,
                            count: currentProject?.counts.suites,
                        },
                    ]
                  : []),
              ...(can?.manageTestPlans || !currentProject
                  ? [
                        {
                            title: 'Test Plans',
                            href: planIndex(project.id),
                            icon: NavIcons.plan,
                            count: currentProject?.counts.plans,
                        },
                    ]
                  : []),
              ...(can?.selectPlans || !currentProject
                  ? [
                        {
                            title: 'Execution',
                            href: selectorIndex(project.id),
                            icon: NavIcons.play,
                        },
                    ]
                  : []),
          ]
        : [];

    const analyticsItems: NavItem[] = project
        ? [
              ...(can?.viewReports || !currentProject
                  ? [
                        {
                            title: 'Standard Reports',
                            href: projectReports(project.id),
                            icon: NavIcons.chart,
                        },
                        {
                            title: 'Custom Reports',
                            href: customReportsIndex(project.id),
                            icon: NavIcons.sparkles,
                        },
                    ]
                  : []),
          ]
        : [];

    const insightItems: NavItem[] = project
        ? [
              ...(can?.viewCodeTrackers || !currentProject
                  ? [
                        {
                            title: 'Code Coverage',
                            href: coverageIndex(project.id),
                            icon: NavIcons.git,
                        },
                    ]
                  : []),
              ...(can?.viewIssueTrackers || !currentProject
                  ? [
                        {
                            title: 'Issues',
                            href: issuesIndex(project.id),
                            icon: NavIcons.bug,
                            count: currentProject?.counts.issues,
                        },
                    ]
                  : []),
          ]
        : [];

    const adminItems: NavItem[] = [
        ...(auth.can.manageUsers
            ? [{ title: 'Users', href: userIndex(), icon: NavIcons.users }]
            : []),
        ...(auth.can.manageRoles
            ? [{ title: 'Roles', href: roleIndex(), icon: NavIcons.shield }]
            : []),
        ...(project
            ? [
                  {
                      title: 'Settings',
                      href: projectSettings(project.id),
                      icon: NavIcons.settings,
                  },
              ]
            : []),
        ...(auth.can.viewEventLog
            ? [{ title: 'Event Log', href: eventIndex(), icon: NavIcons.activity }]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link
                                href={
                                    project
                                        ? projectOverview(project.id)
                                        : userIndex()
                                }
                                prefetch
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-4">
                {workspaceItems.length > 0 && (
                    <NavMain items={workspaceItems} label="Workspace" />
                )}
                {analyticsItems.length > 0 && (
                    <NavMain items={analyticsItems} label="Analytics" />
                )}
                {insightItems.length > 0 && (
                    <NavMain items={insightItems} label="Insights" />
                )}
                {adminItems.length > 0 && (
                    <NavMain items={adminItems} label="Admin" />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
