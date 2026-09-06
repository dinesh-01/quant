import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import KeywordController from '@/actions/App/Http/Controllers/Keywords/KeywordController';
import Heading from '@/components/heading';
import KeywordFormFields from '@/components/keywords/keyword-form-fields';
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
import { edit, index } from '@/routes/keywords';
import { index as projectIndex } from '@/routes/projects';
import type { KeywordDetail, KeywordProject } from '@/types/keyword';

type EditKeywordProps = {
    project: KeywordProject;
    keyword: KeywordDetail;
};

export default function EditKeyword({ project, keyword }: EditKeywordProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Keywords', href: index(project.id) },
            { title: keyword.name, href: edit(keyword.id) },
        ],
    });

    return (
        <>
            <Head title={`Edit ${keyword.name}`} />

            <div className="max-w-2xl space-y-8 p-4">
                <Heading
                    title={`Edit ${keyword.name}`}
                    description="Renaming keeps every existing assignment."
                />

                <Form
                    {...KeywordController.update.form(keyword.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <KeywordFormFields
                                errors={errors}
                                defaults={keyword}
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
                        title="Delete keyword"
                        description="Removes the keyword from the project's vocabulary."
                    />

                    <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                        <div className="space-y-0.5 text-red-600 dark:text-red-100">
                            <p className="font-medium">This cannot be undone</p>

                            <p className="text-sm">
                                {keyword.test_cases_count === 0
                                    ? 'No test case is tagged with it, so nothing else changes.'
                                    : `It is untagged from ${keyword.test_cases_count} ${keyword.test_cases_count === 1 ? 'test case' : 'test cases'}. The cases themselves are not affected.`}
                            </p>
                        </div>

                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="destructive">
                                    Delete keyword
                                </Button>
                            </DialogTrigger>

                            <DialogContent>
                                <DialogTitle>
                                    Delete {keyword.name}?
                                </DialogTitle>

                                <DialogDescription>
                                    {keyword.test_cases_count === 0
                                        ? 'It is not in use.'
                                        : `${keyword.test_cases_count} ${keyword.test_cases_count === 1 ? 'test case loses' : 'test cases lose'} this tag.`}
                                </DialogDescription>

                                <Form
                                    {...KeywordController.destroy.form(
                                        keyword.id,
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
                                                Delete keyword
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
