import { Head, setLayoutProps } from '@inertiajs/react';
import PlanMemberController from '@/actions/App/Http/Controllers/RoleAssignments/PlanMemberController';
import Heading from '@/components/heading';
import MemberManager from '@/components/role-assignments/member-manager';
import { index as planIndex } from '@/routes/plans';
import { index as memberIndex } from '@/routes/plans/members';
import { index as projectIndex } from '@/routes/projects';
import type { AssignableRole, ScopeMember } from '@/types/role-assignment';
import type { PlanProject, PlanSummary } from '@/types/test-plan';

type PlanMembersProps = {
    project: PlanProject;
    plan: Pick<PlanSummary, 'id' | 'name'>;
    members: ScopeMember[];
    roles: AssignableRole[];
    isRestricted: boolean;
};

export default function PlanMembers({
    project,
    plan,
    members,
    roles,
    isRestricted,
}: PlanMembersProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Test plans', href: planIndex(project.id) },
            { title: `${plan.name} members`, href: memberIndex(plan.id) },
        ],
    });

    return (
        <>
            <Head title={`${plan.name} members`} />

            <div className="max-w-3xl space-y-8 p-4">
                <Heading
                    title="Plan members"
                    description={`Roles held for ${plan.name}. A plan role overrides the project role for this plan alone, and can grant either more or less than it.`}
                />

                <MemberManager
                    members={members}
                    roles={roles}
                    isRestricted={isRestricted}
                    scopeNoun="plan"
                    assignForm={() => PlanMemberController.store.form(plan.id)}
                    revokeForm={(userId) =>
                        PlanMemberController.destroy.form([plan.id, userId])
                    }
                    emptyMessage={
                        isRestricted
                            ? 'Nobody holds a role for this private plan yet, so it is reachable only by project administrators.'
                            : 'Nobody holds a plan role yet. Everyone reaches this plan under their project or global role.'
                    }
                />
            </div>
        </>
    );
}
