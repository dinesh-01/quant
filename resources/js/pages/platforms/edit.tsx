import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import PlatformController from '@/actions/App/Http/Controllers/Platforms/PlatformController';
import Heading from '@/components/heading';
import PlatformFormFields from '@/components/platforms/platform-form-fields';
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
import { edit, index } from '@/routes/platforms';
import { index as projectIndex } from '@/routes/projects';
import type { PlatformDetail, PlatformProject } from '@/types/platform';

type EditPlatformProps = {
    project: PlatformProject;
    platform: PlatformDetail;
};

export default function EditPlatform({ project, platform }: EditPlatformProps) {
    const inUseOnPlan = platform.plan_items_count > 0;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Platforms', href: index(project.id) },
            { title: platform.name, href: edit(platform.id) },
        ],
    });

    return (
        <>
            <Head title={`Edit ${platform.name}`} />

            <div className="max-w-2xl space-y-8 p-4">
                <Heading
                    title={`Edit ${platform.name}`}
                    description="Renaming keeps every existing assignment."
                />

                <Form
                    {...PlatformController.update.form(platform.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <PlatformFormFields
                                errors={errors}
                                defaults={platform}
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
                        title="Delete platform"
                        description="Removes the platform from the project's vocabulary."
                    />

                    <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                        <div className="space-y-0.5 text-red-600 dark:text-red-100">
                            <p className="font-medium">
                                {inUseOnPlan
                                    ? 'This platform is still used on a test plan'
                                    : 'This cannot be undone'}
                            </p>

                            <p className="text-sm">
                                {inUseOnPlan
                                    ? `Unlink the ${platform.plan_items_count} ${platform.plan_items_count === 1 ? 'case' : 'cases'} pinned to it before deleting.`
                                    : platform.test_plans_count === 0 &&
                                        platform.test_case_versions_count === 0
                                      ? 'No plan or version is tagged with it, so nothing else changes.'
                                      : 'Plan and version tags are removed. The plans and cases themselves are not affected.'}
                            </p>
                        </div>

                        {inUseOnPlan ? (
                            <Button variant="destructive" disabled>
                                Delete platform
                            </Button>
                        ) : (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="destructive">
                                        Delete platform
                                    </Button>
                                </DialogTrigger>

                                <DialogContent>
                                    <DialogTitle>
                                        Delete {platform.name}?
                                    </DialogTitle>

                                    <DialogDescription>
                                        {platform.test_plans_count === 0 &&
                                        platform.test_case_versions_count === 0
                                            ? 'It is not in use.'
                                            : 'Plan and version tags for this platform are removed.'}
                                    </DialogDescription>

                                    <Form
                                        {...PlatformController.destroy.form(
                                            platform.id,
                                        )}
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
                                                    Delete platform
                                                </Button>
                                            </DialogFooter>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
