import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import TestCaseController from '@/actions/App/Http/Controllers/TestSpecification/TestCaseController';
import { PageHead } from '@/components/chrome/page-head';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show as specificationShow } from '@/routes/specification';

type Props = {
    project: { id: number; name: string; prefix: string };
    suites: { id: number; name: string }[];
    suite_id: number | null;
};

export default function CreateTestCase({ project, suites, suite_id }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test Suites', href: specificationShow(project.id) },
            { title: 'New test case', href: specificationShow(project.id) },
        ],
    });

    const firstSuite =
        suites.find((suite) => suite.id === suite_id) ?? suites[0];

    return (
        <>
            <Head title="New test case" />
            <div className="mx-auto w-full max-w-3xl flex-1 p-6">
                <PageHead
                    title="New test case"
                    description={`Add a case to ${project.name}.`}
                />

                {suites.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        Create a suite first, then add cases to it.
                    </p>
                ) : (
                    <Form
                        {...TestCaseController.store.form(firstSuite.id)}
                        className="bg-card space-y-5 rounded-xl border p-5 shadow-[0_1px_2px_rgba(16,24,40,.06)]"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="test_suite_id">Suite</Label>
                                    <select
                                        id="test_suite_id"
                                        name="__suite"
                                        className="border-border-strong bg-card h-9 w-full rounded-lg border px-3 text-sm"
                                        defaultValue={firstSuite.id}
                                        onChange={(event) => {
                                            const form = event.currentTarget.form;
                                            if (form) {
                                                form.action =
                                                    TestCaseController.store.url(
                                                        Number(event.target.value),
                                                    );
                                            }
                                        }}
                                    >
                                        {suites.map((suite) => (
                                            <option
                                                key={suite.id}
                                                value={suite.id}
                                            >
                                                {suite.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="name">Title</Label>
                                    <Input id="name" name="name" required />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="preconditions">
                                        Preconditions
                                    </Label>
                                    <textarea
                                        id="preconditions"
                                        name="preconditions"
                                        className="border-border-strong bg-card min-h-24 w-full rounded-lg border px-3 py-2 text-sm"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="summary">Summary</Label>
                                    <textarea
                                        id="summary"
                                        name="summary"
                                        className="border-border-strong bg-card min-h-24 w-full rounded-lg border px-3 py-2 text-sm"
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        Create case
                                    </Button>
                                    <Button variant="ghost" asChild>
                                        <Link
                                            href={specificationShow(project.id)}
                                        >
                                            Cancel
                                        </Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}
