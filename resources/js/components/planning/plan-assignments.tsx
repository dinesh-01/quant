import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TesterAssignmentController from '@/actions/App/Http/Controllers/TesterAssignments/TesterAssignmentController';
import { show } from '@/routes/plans';
import type {
    AssignableTester,
    AssignmentStatusOption,
    PlanBuildSummary,
    PlanContentsAbilities,
    PlanItemSummary,
    TesterAssignmentSummary,
} from '@/types/test-plan';

const selectClasses =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

export default function PlanAssignments({
    planId,
    builds,
    items,
    testers,
    assignments,
    statuses,
    selectedBuildId,
    can,
}: {
    planId: number;
    builds: PlanBuildSummary[];
    items: PlanItemSummary[];
    testers: AssignableTester[];
    assignments: TesterAssignmentSummary[];
    statuses: AssignmentStatusOption[];
    selectedBuildId: number | null;
    can: PlanContentsAbilities;
}) {
    const buildAssignments = assignments.filter(
        (assignment) => assignment.build_id === selectedBuildId,
    );

    if (builds.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Tester assignments</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-muted-foreground text-sm">
                        Create a build before assigning testers. Assignments
                        are per build so the same people can be asked again on
                        the next snapshot.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Tester assignments</CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">
                <Form
                    action={show.url(planId)}
                    method="get"
                    className="grid gap-2 sm:max-w-xs"
                >
                    <Label htmlFor="build">Build</Label>
                    <select
                        id="build"
                        name="build"
                        defaultValue={selectedBuildId ?? ''}
                        className={selectClasses}
                        onChange={(event) => event.currentTarget.form?.submit()}
                    >
                        {builds.map((build) => (
                            <option key={build.id} value={build.id}>
                                {build.name}
                            </option>
                        ))}
                    </select>
                </Form>

                {can.assignTesters && testers.length > 0 && items.length > 0 && (
                    <Form
                        {...TesterAssignmentController.store.form(planId)}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="grid gap-3 sm:grid-cols-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="build_id"
                                    value={selectedBuildId ?? ''}
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="test_plan_item_id">
                                        Case
                                    </Label>
                                    <select
                                        id="test_plan_item_id"
                                        name="test_plan_item_id"
                                        className={selectClasses}
                                        required
                                    >
                                        {items.map((item) => (
                                            <option key={item.id} value={item.id}>
                                                {item.full_external_id}{' '}
                                                {item.test_case_name}
                                                {item.platform
                                                    ? ` (${item.platform.name})`
                                                    : ''}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.test_plan_item_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="user_id">Tester</Label>
                                    <select
                                        id="user_id"
                                        name="user_id"
                                        className={selectClasses}
                                        required
                                    >
                                        {testers.map((tester) => (
                                            <option
                                                key={tester.id}
                                                value={tester.id}
                                            >
                                                {tester.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.user_id} />
                                    <InputError message={errors.tester} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Status</Label>
                                    <select
                                        id="status"
                                        name="status"
                                        defaultValue="open"
                                        className={selectClasses}
                                    >
                                        {statuses.map((status) => (
                                            <option
                                                key={status.value}
                                                value={status.value}
                                            >
                                                {status.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="deadline_at">Deadline</Label>
                                    <Input
                                        id="deadline_at"
                                        name="deadline_at"
                                        type="date"
                                    />
                                </div>

                                <div>
                                    <Button disabled={processing}>
                                        Assign tester
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}

                {can.assignTesters && builds.length > 1 && (
                    <Form
                        {...TesterAssignmentController.copy.form(planId)}
                        options={{ preserveScroll: true }}
                        className="grid gap-3 sm:grid-cols-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="source_build_id">
                                        Copy from
                                    </Label>
                                    <select
                                        id="source_build_id"
                                        name="source_build_id"
                                        className={selectClasses}
                                        required
                                    >
                                        {builds.map((build) => (
                                            <option
                                                key={build.id}
                                                value={build.id}
                                            >
                                                {build.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="target_build_id">
                                        Copy onto
                                    </Label>
                                    <select
                                        id="target_build_id"
                                        name="target_build_id"
                                        defaultValue={selectedBuildId ?? ''}
                                        className={selectClasses}
                                        required
                                    >
                                        {builds.map((build) => (
                                            <option
                                                key={build.id}
                                                value={build.id}
                                            >
                                                {build.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.target_build}
                                    />
                                </div>

                                <div className="flex items-end">
                                    <Button
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        Copy assignments
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}

                {items.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        Link cases to this plan before assigning testers.
                    </p>
                ) : (
                    <ul className="divide-y rounded-md border">
                        {items.map((item) => {
                            const assigned = buildAssignments.filter(
                                (assignment) =>
                                    assignment.test_plan_item_id === item.id,
                            );

                            return (
                                <li key={item.id} className="space-y-3 p-3">
                                    <p className="font-medium">
                                        {item.full_external_id}{' '}
                                        {item.test_case_name}
                                        {item.platform
                                            ? ` · ${item.platform.name}`
                                            : ''}
                                    </p>

                                    {assigned.length === 0 ? (
                                        <p className="text-muted-foreground text-sm">
                                            Nobody assigned on this build.
                                        </p>
                                    ) : (
                                        <ul className="space-y-2">
                                            {assigned.map((assignment) => (
                                                <li
                                                    key={assignment.id}
                                                    className="flex flex-wrap items-end gap-2"
                                                >
                                                    {can.assignTesters ? (
                                                        <Form
                                                            {...TesterAssignmentController.update.form(
                                                                assignment.id,
                                                            )}
                                                            options={{
                                                                preserveScroll: true,
                                                            }}
                                                            className="flex flex-wrap items-end gap-2"
                                                        >
                                                            {({
                                                                processing,
                                                            }) => (
                                                                <>
                                                                    <p className="text-sm">
                                                                        {
                                                                            assignment.user_name
                                                                        }
                                                                    </p>
                                                                    <select
                                                                        name="status"
                                                                        defaultValue={
                                                                            assignment.status
                                                                        }
                                                                        className={
                                                                            selectClasses
                                                                        }
                                                                    >
                                                                        {statuses.map(
                                                                            (
                                                                                status,
                                                                            ) => (
                                                                                <option
                                                                                    key={
                                                                                        status.value
                                                                                    }
                                                                                    value={
                                                                                        status.value
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        status.label
                                                                                    }
                                                                                </option>
                                                                            ),
                                                                        )}
                                                                    </select>
                                                                    <Input
                                                                        name="deadline_at"
                                                                        type="date"
                                                                        defaultValue={
                                                                            assignment.deadline_at ??
                                                                            ''
                                                                        }
                                                                    />
                                                                    <Button
                                                                        size="sm"
                                                                        variant="secondary"
                                                                        disabled={
                                                                            processing
                                                                        }
                                                                    >
                                                                        Save
                                                                    </Button>
                                                                </>
                                                            )}
                                                        </Form>
                                                    ) : (
                                                        <p className="text-sm">
                                                            {
                                                                assignment.user_name
                                                            }{' '}
                                                            · {assignment.status}
                                                            {assignment.deadline_at
                                                                ? ` · due ${assignment.deadline_at}`
                                                                : ''}
                                                        </p>
                                                    )}

                                                    {can.assignTesters && (
                                                        <Form
                                                            {...TesterAssignmentController.destroy.form(
                                                                assignment.id,
                                                            )}
                                                            options={{
                                                                preserveScroll: true,
                                                            }}
                                                        >
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                            >
                                                                Remove
                                                            </Button>
                                                        </Form>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
