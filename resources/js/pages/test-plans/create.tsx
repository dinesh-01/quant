import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import TestPlanController from '@/actions/App/Http/Controllers/TestPlans/TestPlanController';
import Heading from '@/components/heading';
import PlanFormFields from '@/components/test-plans/plan-form-fields';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/plans';
import { index as projectIndex } from '@/routes/projects';
import type { PlanProject } from '@/types/test-plan';

type CreatePlanProps = {
    project: PlanProject;
};

export default function CreateTestPlan({ project }: CreatePlanProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Test plans', href: index(project.id) },
            { title: 'New plan', href: create(project.id) },
        ],
    });

    return (
        <>
            <Head title="New test plan" />

            <div className="max-w-2xl space-y-6 p-4">
                <Heading
                    title="New test plan"
                    description={`A round of testing against ${project.name}.`}
                />

                <Form
                    {...TestPlanController.store.form(project.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <PlanFormFields errors={errors} />

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    Create plan
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
