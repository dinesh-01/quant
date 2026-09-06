import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import PlatformController from '@/actions/App/Http/Controllers/Platforms/PlatformController';
import Heading from '@/components/heading';
import PlatformFormFields from '@/components/platforms/platform-form-fields';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/platforms';
import { index as projectIndex } from '@/routes/projects';
import type { PlatformProject } from '@/types/platform';

type CreatePlatformProps = {
    project: PlatformProject;
};

export default function CreatePlatform({ project }: CreatePlatformProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Platforms', href: index(project.id) },
            { title: 'New platform', href: create(project.id) },
        ],
    });

    return (
        <>
            <Head title="New platform" />

            <div className="max-w-2xl space-y-6 p-4">
                <Heading
                    title="New platform"
                    description={`An environment for ${project.name}.`}
                />

                <Form
                    {...PlatformController.store.form(project.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <PlatformFormFields errors={errors} />

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    Create platform
                                </Button>

                                <Button asChild variant="ghost">
                                    <Link href={index(project.id)}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
