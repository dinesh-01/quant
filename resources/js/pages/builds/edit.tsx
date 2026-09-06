import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import BuildController from '@/actions/App/Http/Controllers/Builds/BuildController';
import BuildFormFields from '@/components/builds/build-form-fields';
import Heading from '@/components/heading';
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
import { Separator } from '@/components/ui/separator';
import { edit } from '@/routes/builds';
import { index as planIndex, show as planShow } from '@/routes/plans';
import { index as projectIndex } from '@/routes/projects';
import type { PlanBuildSummary } from '@/types/test-plan';

type EditBuildProps = {
    project: { id: number; name: string };
    plan: { id: number; name: string };
    build: PlanBuildSummary;
};

export default function EditBuild({ project, plan, build }: EditBuildProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Test plans', href: planIndex(project.id) },
            { title: plan.name, href: planShow(plan.id) },
            { title: build.name, href: edit(build.id) },
        ],
    });

    return (
        <>
            <Head title={`Edit ${build.name}`} />

            <div className="max-w-2xl space-y-8 p-4">
                <Heading
                    title={`Edit ${build.name}`}
                    description={`A build of ${plan.name}.`}
                />

                <Form
                    {...BuildController.update.form(build.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <BuildFormFields
                                errors={errors}
                                defaults={build}
                            />

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    Save changes
                                </Button>

                                <Button asChild variant="ghost">
                                    <Link href={planShow(plan.id)}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <Separator />

                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Delete build"
                        description="Removes the build from this plan."
                    />

                    <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                        <div className="space-y-0.5 text-red-600 dark:text-red-100">
                            <p className="font-medium">This cannot be undone</p>

                            <p className="text-sm">
                                Once execution history exists, a later slice
                                will refuse this. Closing the build is the
                                reversible alternative.
                            </p>
                        </div>

                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="destructive">
                                    Delete build
                                </Button>
                            </DialogTrigger>

                            <DialogContent>
                                <DialogTitle>Delete {build.name}?</DialogTitle>

                                <DialogDescription>
                                    The build is removed from {plan.name}.
                                </DialogDescription>

                                <Form
                                    {...BuildController.destroy.form(build.id)}
                                >
                                    {({ processing }) => (
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
                                                Delete build
                                            </Button>
                                        </DialogFooter>
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
