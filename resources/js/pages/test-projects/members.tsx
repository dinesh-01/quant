import { Head, setLayoutProps } from '@inertiajs/react';
import ProjectMemberController from '@/actions/App/Http/Controllers/RoleAssignments/ProjectMemberController';
import Heading from '@/components/heading';
import MemberManager from '@/components/role-assignments/member-manager';
import { index as projectIndex } from '@/routes/projects';
import { index as memberIndex } from '@/routes/projects/members';
import type { AssignableRole, ScopeMember } from '@/types/role-assignment';
import type { PlanProject } from '@/types/test-plan';

type ProjectMembersProps = {
    project: PlanProject;
    members: ScopeMember[];
    roles: AssignableRole[];
    isRestricted: boolean;
};

export default function ProjectMembers({
    project,
    members,
    roles,
    isRestricted,
}: ProjectMembersProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Members', href: memberIndex(project.id) },
        ],
    });

    return (
        <>
            <Head title={`${project.name} members`} />

            <div className="max-w-3xl space-y-8 p-4">
                <Heading
                    title="Project members"
                    description={`Roles held for ${project.name}. A project role replaces the person's global role for everything in this project.`}
                />

                <MemberManager
                    members={members}
                    roles={roles}
                    isRestricted={isRestricted}
                    scopeNoun="project"
                    assignForm={() =>
                        ProjectMemberController.store.form(project.id)
                    }
                    revokeForm={(userId) =>
                        ProjectMemberController.destroy.form([
                            project.id,
                            userId,
                        ])
                    }
                    emptyMessage={
                        isRestricted
                            ? 'Nobody holds a role for this private project yet, so only project administrators can reach it.'
                            : 'Nobody holds a project role yet. Everyone reaches this project under their global role.'
                    }
                />
            </div>
        </>
    );
}
