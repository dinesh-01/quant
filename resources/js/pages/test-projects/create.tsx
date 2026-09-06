import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import TestProjectController from '@/actions/App/Http/Controllers/TestProjects/TestProjectController';
import Heading from '@/components/heading';
import ProjectFormFields from '@/components/test-projects/project-form-fields';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/projects';

export default function CreateTestProject() {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: index() },
            { title: 'New project', href: create() },
        ],
    });

    return (
        <>
            <Head title="New test project" />

            <div className="max-w-2xl space-y-6 p-4">
                <Heading
                    title="New test project"
                    description="A project holds its own test suites, cases and plans."
                />

                <Form
                    {...TestProjectController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <ProjectFormFields errors={errors} />

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    Create project
                                </Button>

                                <Button asChild variant="ghost">
                                    <Link href={index()}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
