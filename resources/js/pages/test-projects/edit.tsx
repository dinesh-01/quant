import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import AttachmentController from '@/actions/App/Http/Controllers/Attachments/AttachmentController';
import TestProjectController from '@/actions/App/Http/Controllers/TestProjects/TestProjectController';
import AttachmentList from '@/components/attachments/attachment-list';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import ProjectFormFields from '@/components/test-projects/project-form-fields';
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
import { edit, index } from '@/routes/projects';
import type { AttachmentRules } from '@/types/attachment';
import type { ProjectFormValues } from '@/types/test-project';

type EditProjectProps = {
    project: ProjectFormValues;
    attachmentRules: AttachmentRules;
};

export default function EditTestProject({
    project,
    attachmentRules,
}: EditProjectProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: index() },
            { title: project.name, href: edit(project.id) },
        ],
    });

    return (
        <>
            <Head title={`Edit ${project.name}`} />

            <div className="max-w-2xl space-y-8 p-4">
                <Heading
                    title={`Edit ${project.name}`}
                    description="Project settings and visibility."
                />

                <Form
                    {...TestProjectController.update.form(project.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <ProjectFormFields
                                errors={errors}
                                defaults={project}
                                prefixLocked={project.prefix_locked}
                            />

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    Save changes
                                </Button>

                                <Button asChild variant="ghost">
                                    <Link href={index()}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <Separator />

                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Attachments"
                        description="Files that belong to the project as a whole rather than to one test case."
                    />

                    <AttachmentList
                        attachments={project.attachments}
                        rules={attachmentRules}
                        upload={AttachmentController.storeForProject.form(
                            project.id,
                        )}
                        canManage
                        describedAs="this project"
                    />
                </div>

                <Separator />

                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Delete project"
                        description="Removes the project and everything inside it."
                    />

                    <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                        <div className="space-y-0.5 text-red-600 dark:text-red-100">
                            <p className="font-medium">This cannot be undone</p>
                            <p className="text-sm">
                                {project.test_cases_count} test cases, their
                                versions and steps, every test suite and every
                                test plan in this project are deleted with it.
                                Deactivating the project instead hides it
                                without losing anything.
                            </p>
                        </div>

                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="destructive">
                                    Delete project
                                </Button>
                            </DialogTrigger>

                            <DialogContent>
                                <DialogTitle>
                                    Delete {project.name}?
                                </DialogTitle>

                                <DialogDescription>
                                    Type the project name to confirm. Everything
                                    in it is deleted permanently.
                                </DialogDescription>

                                <Form
                                    {...TestProjectController.destroy.form(
                                        project.id,
                                    )}
                                    className="space-y-6"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="confirm_name">
                                                    Project name
                                                </Label>

                                                <Input
                                                    id="confirm_name"
                                                    name="confirm_name"
                                                    autoComplete="off"
                                                    placeholder={project.name}
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
                                                    Delete project
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
