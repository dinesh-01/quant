import { Form } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import MilestoneController from '@/actions/App/Http/Controllers/Milestones/MilestoneController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PlanContentsAbilities, PlanMilestone } from '@/types/test-plan';

export default function PlanMilestones({
    planId,
    milestones,
    can,
}: {
    planId: number;
    milestones: PlanMilestone[];
    can: PlanContentsAbilities;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Milestones</CardTitle>
            </CardHeader>

            <CardContent className="space-y-4">
                {milestones.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No milestones yet. A milestone is a target date with
                        completion percentages for high, medium and low urgency
                        cases.
                    </p>
                ) : (
                    <ul className="space-y-4">
                        {milestones.map((milestone) => (
                            <li
                                key={milestone.id}
                                className="space-y-3 rounded-md border p-3"
                            >
                                {can.manageMilestones ? (
                                    <Form
                                        {...MilestoneController.update.form(
                                            milestone.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                        className="space-y-3"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <MilestoneFields
                                                    prefix={`edit-${milestone.id}`}
                                                    milestone={milestone}
                                                    errors={errors}
                                                />

                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    disabled={processing}
                                                >
                                                    Save milestone
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                ) : (
                                    <div className="space-y-1">
                                        <p className="font-medium">
                                            {milestone.name}
                                        </p>
                                        <p className="text-muted-foreground text-sm">
                                            Target {milestone.target_date}
                                            {milestone.start_date
                                                ? ` · start ${milestone.start_date}`
                                                : ''}
                                            {` · H ${milestone.high_percent}% / M ${milestone.medium_percent}% / L ${milestone.low_percent}%`}
                                        </p>
                                    </div>
                                )}

                                {can.manageMilestones && (
                                    <Form
                                        {...MilestoneController.destroy.form(
                                            milestone.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                disabled={processing}
                                            >
                                                <Trash2 className="size-4" />
                                                Remove
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                {can.manageMilestones && (
                    <Form
                        {...MilestoneController.store.form(planId)}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="space-y-3 rounded-md border p-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <p className="text-sm font-medium">
                                    Add milestone
                                </p>

                                <MilestoneFields
                                    prefix="new"
                                    errors={errors}
                                />

                                <Button size="sm" disabled={processing}>
                                    <Plus className="size-4" />
                                    Add milestone
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </CardContent>
        </Card>
    );
}

function MilestoneFields({
    prefix,
    milestone,
    errors,
}: {
    prefix: string;
    milestone?: PlanMilestone;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor={`${prefix}-name`}>Name</Label>
                <Input
                    id={`${prefix}-name`}
                    name="name"
                    defaultValue={milestone?.name ?? ''}
                    required
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-target`}>Target date</Label>
                <Input
                    id={`${prefix}-target`}
                    name="target_date"
                    type="date"
                    defaultValue={milestone?.target_date ?? ''}
                    required
                />
                <InputError message={errors.target_date} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-start`}>Start date</Label>
                <Input
                    id={`${prefix}-start`}
                    name="start_date"
                    type="date"
                    defaultValue={milestone?.start_date ?? ''}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-high`}>High %</Label>
                <Input
                    id={`${prefix}-high`}
                    name="high_percent"
                    type="number"
                    min={0}
                    max={100}
                    defaultValue={milestone?.high_percent ?? 100}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-medium`}>Medium %</Label>
                <Input
                    id={`${prefix}-medium`}
                    name="medium_percent"
                    type="number"
                    min={0}
                    max={100}
                    defaultValue={milestone?.medium_percent ?? 80}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-low`}>Low %</Label>
                <Input
                    id={`${prefix}-low`}
                    name="low_percent"
                    type="number"
                    min={0}
                    max={100}
                    defaultValue={milestone?.low_percent ?? 50}
                />
            </div>
        </div>
    );
}
