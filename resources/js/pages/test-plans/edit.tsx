import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import TestPlanController from '@/actions/App/Http/Controllers/TestPlans/TestPlanController';
import CustomFieldInputs from '@/components/custom-fields/custom-field-inputs';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PlanFormFields from '@/components/test-plans/plan-form-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { edit, index } from '@/routes/plans';
import { index as projectIndex } from '@/routes/projects';
import type { CustomFieldInput } from '@/types/custom-field';
import type { PlanProject, PlanSummary } from '@/types/test-plan';

type EditPlanProps = {
    project: PlanProject;
    plan: PlanSummary;
    customFields: CustomFieldInput[];
};

export default function EditTestPlan({
    project,
    plan,
    customFields,
}: EditPlanProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Test plans', href: index(project.id) },
            { title: plan.name, href: edit(plan.id) },
        ],
    });

    return (
        <>
            <Head title={`Edit ${plan.name}`} />

            <div className="max-w-2xl space-y-8 p-4">
                <Heading
                    title={`Edit ${plan.name}`}
                    description="Plan settings, visibility and execution state."
                />

                <Form
                    {...TestPlanController.update.form(plan.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <PlanFormFields errors={errors} defaults={plan} />

                            <CustomFieldInputs
                                fields={customFields}
                                errors={errors}
                            />

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    Save changes
                                </Button>

                                <Button asChild variant="ghost">
                                    <Link href={index(project.id)}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <Separator />

                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Delete plan"
                        description="Closing the plan is almost always what you want instead."
                    />

                    <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                        <div className="space-y-0.5 text-red-600 dark:text-red-100">
                            <p className="font-medium">This cannot be undone</p>
                            <p className="text-sm">
                                The plan's role assignments go with it, and once
                                execution exists so will its builds, its linked
                                test case versions and its results. Test cases
                                themselves live in the project and are not
                                affected.
                            </p>
                        </div>

                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="destructive">
                                    Delete plan
                                </Button>
                            </DialogTrigger>

                            <DialogContent>
                                <DialogTitle>Delete {plan.name}?</DialogTitle>

                                <DialogDescription>
                                    Type the plan name to confirm.
                                </DialogDescription>

                                <Form
                                    {...TestPlanController.destroy.form(
                                        plan.id,
                                    )}
                                    className="space-y-6"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="confirm_name">
                                                    Plan name
                                                </Label>

                                                <Input
                                                    id="confirm_name"
                                                    name="confirm_name"
                                                    autoComplete="off"
                                                    placeholder={plan.name}
                                                />

                                                <InputError
                                                    message={
                                                        errors.confirm_name
                                                    }
                                                />
                                            </div>

                                            <DialogFooter className="gap-2">
                                                <DialogClose asChild>
                                                    <Button variant="secondary">
                                                        Cancel
                                                    </Button>
                                                </DialogClose>

                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    disabled={processing}
                                                >
                                                    Delete plan
                                                </Button>
                                            </DialogFooter>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>
            </div>
        </>
    );
}
