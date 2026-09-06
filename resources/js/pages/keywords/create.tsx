import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import KeywordController from '@/actions/App/Http/Controllers/Keywords/KeywordController';
import Heading from '@/components/heading';
import KeywordFormFields from '@/components/keywords/keyword-form-fields';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/keywords';
import { index as projectIndex } from '@/routes/projects';
import type { KeywordProject } from '@/types/keyword';

type CreateKeywordProps = {
    project: KeywordProject;
};

export default function CreateKeyword({ project }: CreateKeywordProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Keywords', href: index(project.id) },
            { title: 'New keyword', href: create(project.id) },
        ],
    });

    return (
        <>
            <Head title="New keyword" />

            <div className="max-w-2xl space-y-6 p-4">
                <Heading
                    title="New keyword"
                    description={`A tag for ${project.name}'s test cases.`}
                />

                <Form
                    {...KeywordController.store.form(project.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <KeywordFormFields errors={errors} />

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    Create keyword
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
