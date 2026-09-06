import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import BuildController from '@/actions/App/Http/Controllers/Builds/BuildController';
import BuildFormFields from '@/components/builds/build-form-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { create } from '@/routes/builds';
import { index as planIndex, show as planShow } from '@/routes/plans';
import { index as projectIndex } from '@/routes/projects';

type CreateBuildProps = {
    project: { id: number; name: string };
    plan: { id: number; name: string };
};

export default function CreateBuild({ project, plan }: CreateBuildProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Test plans', href: planIndex(project.id) },
            { title: plan.name, href: planShow(plan.id) },
            { title: 'New build', href: create(plan.id) },
        ],
    });

    return (
        <>
            <Head title="New build" />

            <div className="max-w-2xl space-y-6 p-4">
                <Heading
                    title="New build"
                    description={`A snapshot of the software ${plan.name} will run against.`}
                />

                <Form
                    {...BuildController.store.form(plan.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <BuildFormFields errors={errors} />

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    Create build
                                </Button>

                                <Button asChild variant="ghost">
                                    <Link href={planShow(plan.id)}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
