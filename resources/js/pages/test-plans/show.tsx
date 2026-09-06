import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { ChartColumn, Pencil, Users } from 'lucide-react';
import Heading from '@/components/heading';
import AttachmentController from '@/actions/App/Http/Controllers/Attachments/AttachmentController';
import AttachmentList from '@/components/attachments/attachment-list';
import PlanAssignments from '@/components/planning/plan-assignments';
import PlanBuilds from '@/components/planning/plan-builds';
import PlanItems from '@/components/planning/plan-items';
import PlanMilestones from '@/components/planning/plan-milestones';
import PlanPlatforms from '@/components/planning/plan-platforms';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { AttachmentRules, AttachmentSummary } from '@/types/attachment';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit, index, show } from '@/routes/plans';
import { plan as planReports } from '@/routes/reports';
import { index as memberIndex } from '@/routes/plans/members';
import { index as projectIndex } from '@/routes/projects';
import type {
    AssignableTester,
    AssignmentStatusOption,
    LinkableCase,
    PlanBuildSummary,
    PlanContents,
    PlanContentsAbilities,
    PlanContentsProject,
    PlanItemSummary,
    PlanMilestone,
    PlanPlatformOption,
    TesterAssignmentSummary,
} from '@/types/test-plan';

type PlanShowProps = {
    project: PlanContentsProject;
    plan: PlanContents;
    platforms: PlanPlatformOption[];
    builds: PlanBuildSummary[];
    items: PlanItemSummary[];
    linkable: LinkableCase[];
    can: PlanContentsAbilities;
    selectedBuildId: number | null;
    testers: AssignableTester[];
    assignments: TesterAssignmentSummary[];
    assignmentStatuses: AssignmentStatusOption[];
    milestones: PlanMilestone[];
    attachments: AttachmentSummary[];
    attachmentRules: AttachmentRules;
};

/**
 * The plan as a workspace: its platforms, builds and linked cases.
 *
 * Settings stay on the edit screen. This is what the plan list points at
 * once a plan exists.
 */
export default function TestPlanShow({
    project,
    plan,
    platforms,
    builds,
    items,
    linkable,
    can,
    selectedBuildId,
    testers,
    assignments,
    assignmentStatuses,
    milestones,
    attachments,
    attachmentRules,
}: PlanShowProps) {
    const { currentProject } = usePage().props;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Test plans', href: index(project.id) },
            { title: plan.name, href: show(plan.id) },
        ],
    });

    return (
        <>
            <Head title={plan.name} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <Heading
                                title={plan.name}
                                description={`Contents of this round of testing in ${project.name}.`}
                            />

                            {!plan.is_open && (
                                <Badge variant="secondary">Closed</Badge>
                            )}
                        </div>
                    </div>

                    <div className="flex shrink-0 gap-1">
                        {currentProject?.can.viewReports && (
                            <Button asChild size="sm" variant="ghost">
                                <Link href={planReports(plan.id)}>
                                    <ChartColumn className="size-4" />
                                    Reports
                                </Link>
                            </Button>
                        )}

                        {can.editPlan && (
                            <>
                                <Button asChild size="sm" variant="ghost">
                                    <Link href={memberIndex(plan.id)}>
                                        <Users className="size-4" />
                                        Members
                                    </Link>
                                </Button>

                                <Button asChild size="sm" variant="ghost">
                                    <Link href={edit(plan.id)}>
                                        <Pencil className="size-4" />
                                        Edit
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                <div className="grid gap-6">
                    <PlanPlatforms
                        projectId={project.id}
                        plan={plan}
                        platforms={platforms}
                        can={can}
                    />

                    <PlanBuilds
                        planId={plan.id}
                        builds={builds}
                        can={can}
                    />

                    <PlanItems
                        plan={plan}
                        items={items}
                        linkable={linkable}
                        platforms={platforms}
                        can={can}
                    />

                    <PlanAssignments
                        planId={plan.id}
                        builds={builds}
                        items={items}
                        testers={testers}
                        assignments={assignments}
                        statuses={assignmentStatuses}
                        selectedBuildId={selectedBuildId}
                        can={can}
                    />

                    <PlanMilestones
                        planId={plan.id}
                        milestones={milestones}
                        can={can}
                    />

                    {can.viewAttachments && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Attachments</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <AttachmentList
                                    attachments={attachments}
                                    rules={attachmentRules}
                                    upload={AttachmentController.storeForPlan.form(
                                        plan.id,
                                    )}
                                    canManage={can.manageAttachments}
                                    describedAs="this plan"
                                />
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}
