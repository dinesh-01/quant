import { Form } from '@inertiajs/react';
import TestPlanItemController from '@/actions/App/Http/Controllers/TestPlanItems/TestPlanItemController';
import InputError from '@/components/input-error';
import ReorderControls from '@/components/test-specification/reorder-controls';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import type {
    LinkableCase,
    PlanContents,
    PlanContentsAbilities,
    PlanItemSummary,
    PlanPlatformOption,
} from '@/types/test-plan';

const selectClasses =
    'border-input bg-background focus-visible:ring-ring rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none';

const urgencyLabels: Record<string, string> = {
    low: 'Low',
    medium: 'Medium',
    high: 'High',
};

export default function PlanItems({
    plan,
    items,
    linkable,
    platforms,
    can,
}: {
    plan: PlanContents;
    items: PlanItemSummary[];
    linkable: LinkableCase[];
    platforms: PlanPlatformOption[];
    can: PlanContentsAbilities;
}) {
    const assignedPlatforms = platforms.filter((platform) => platform.assigned);
    const order = items.map((item) => item.id);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Linked test cases</CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">
                {can.planTestCases && (
                    <Form
                        {...TestPlanItemController.store.form(plan.id)}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="test_case_version_id">
                                            Test case
                                        </Label>

                                        <select
                                            id="test_case_version_id"
                                            name="test_case_version_id"
                                            required
                                            className={selectClasses}
                                            defaultValue=""
                                        >
                                            <option value="" disabled>
                                                {linkable.length === 0
                                                    ? 'No test cases in this project'
                                                    : 'Choose a test case'}
                                            </option>

                                            {linkable.map((each) => (
                                                <option
                                                    key={each.version_id}
                                                    value={each.version_id}
                                                >
                                                    {each.full_external_id}{' '}
                                                    {each.name} (v
                                                    {each.version})
                                                </option>
                                            ))}
                                        </select>

                                        <InputError
                                            message={
                                                errors.test_case_version_id
                                            }
                                        />
                                    </div>

                                    {assignedPlatforms.length > 0 && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="platform_id">
                                                Platform
                                            </Label>

                                            <select
                                                id="platform_id"
                                                name="platform_id"
                                                required
                                                className={selectClasses}
                                                defaultValue=""
                                            >
                                                <option value="" disabled>
                                                    Choose a platform
                                                </option>

                                                {assignedPlatforms.map(
                                                    (platform) => (
                                                        <option
                                                            key={platform.id}
                                                            value={platform.id}
                                                        >
                                                            {platform.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>

                                            <InputError
                                                message={errors.platform_id}
                                            />
                                        </div>
                                    )}

                                    <div className="grid gap-2">
                                        <Label htmlFor="urgency">
                                            Urgency
                                        </Label>

                                        <select
                                            id="urgency"
                                            name="urgency"
                                            className={selectClasses}
                                            defaultValue="medium"
                                        >
                                            {Object.entries(urgencyLabels).map(
                                                ([value, label]) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </div>
                                </div>

                                <Button
                                    size="sm"
                                    disabled={
                                        processing || linkable.length === 0
                                    }
                                >
                                    Link test case
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                {items.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No test cases are linked to this plan yet.
                    </p>
                ) : (
                    <ul className="divide-y rounded-md border">
                        {items.map((item) => (
                            <li
                                key={item.id}
                                className="flex flex-col gap-3 p-3 lg:flex-row lg:items-center lg:justify-between"
                            >
                                <div className="min-w-0 space-y-1">
                                    <p className="font-medium">
                                        <span className="text-muted-foreground mr-2 font-mono text-sm">
                                            {item.full_external_id}
                                        </span>
                                        {item.test_case_name}
                                    </p>

                                    <p className="text-muted-foreground text-sm">
                                        Version {item.version}
                                        {item.latest_version > item.version &&
                                            ` · newest is ${item.latest_version}`}
                                        {item.platform
                                            ? ` · ${item.platform.name}`
                                            : ''}
                                    </p>
                                </div>

                                <div className="flex flex-wrap items-center gap-2">
                                    {can.planTestCases && (
                                        <ReorderControls
                                            action={TestPlanItemController.reorder.form(
                                                plan.id,
                                            )}
                                            order={order}
                                            id={item.id}
                                            label={item.test_case_name}
                                        />
                                    )}

                                    {can.setUrgency ? (
                                        <Form
                                            {...TestPlanItemController.updateUrgency.form(
                                                item.id,
                                            )}
                                            options={{ preserveScroll: true }}
                                            className="flex items-center gap-2"
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <select
                                                        name="urgency"
                                                        defaultValue={
                                                            item.urgency
                                                        }
                                                        aria-label={`Urgency for ${item.test_case_name}`}
                                                        className={selectClasses}
                                                    >
                                                        {Object.entries(
                                                            urgencyLabels,
                                                        ).map(
                                                            ([
                                                                value,
                                                                label,
                                                            ]) => (
                                                                <option
                                                                    key={value}
                                                                    value={
                                                                        value
                                                                    }
                                                                >
                                                                    {label}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>

                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                        disabled={processing}
                                                    >
                                                        Save
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    ) : (
                                        <span className="text-muted-foreground text-sm">
                                            {urgencyLabels[item.urgency] ??
                                                item.urgency}
                                        </span>
                                    )}

                                    {can.updateLinkedVersions &&
                                        item.latest_version > item.version && (
                                            <Form
                                                {...TestPlanItemController.updateVersion.form(
                                                    item.id,
                                                )}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                        disabled={processing}
                                                    >
                                                        Bump to v
                                                        {item.latest_version}
                                                    </Button>
                                                )}
                                            </Form>
                                        )}

                                    {can.planTestCases && (
                                        <Form
                                            {...TestPlanItemController.destroy.form(
                                                item.id,
                                            )}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={processing}
                                                >
                                                    Unlink
                                                </Button>
                                            )}
                                        </Form>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
