import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import CustomFieldController from '@/actions/App/Http/Controllers/CustomFields/CustomFieldController';
import CustomFieldFormFields from '@/components/custom-fields/custom-field-form-fields';
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
import { edit, index } from '@/routes/custom-fields';
import type {
    CustomFieldEntityOption,
    CustomFieldFormValues,
    CustomFieldTypeOption,
} from '@/types/custom-field';

type EditCustomFieldProps = {
    field: CustomFieldFormValues;
    types: CustomFieldTypeOption[];
    entities: CustomFieldEntityOption[];
};

export default function EditCustomField({
    field,
    types,
    entities,
}: EditCustomFieldProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Custom fields', href: index() },
            { title: field.label, href: edit(field.id) },
        ],
    });

    const reach =
        field.projects_count === 1
            ? '1 project'
            : `${field.projects_count} projects`;

    return (
        <>
            <Head title={`Edit ${field.label}`} />

            <div className="max-w-2xl space-y-8 p-4">
                <Heading
                    title={`Edit ${field.label}`}
                    description={`Enabled in ${reach}, all of which see this change.`}
                />

                <Form
                    {...CustomFieldController.update.form(field.id)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <CustomFieldFormFields
                                errors={errors}
                                types={types}
                                entities={entities}
                                defaults={field}
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
                        title="Delete field"
                        description="Removes the definition everywhere, along with every answer given to it."
                    />

                    <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                        <div className="space-y-0.5 text-red-600 dark:text-red-100">
                            <p className="font-medium">This cannot be undone</p>

                            <p className="text-sm">
                                {field.answers_count === 0
                                    ? 'Nothing has been filled in, so no answers are lost.'
                                    : `${field.answers_count} ${field.answers_count === 1 ? 'answer' : 'answers'} across ${reach} ${field.answers_count === 1 ? 'is' : 'are'} deleted with it. To stop a project recording the field while keeping what it has already recorded, switch the field off in that project instead.`}
                            </p>
                        </div>

                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="destructive">
                                    Delete field
                                </Button>
                            </DialogTrigger>

                            <DialogContent>
                                <DialogTitle>Delete {field.label}?</DialogTitle>

                                <DialogDescription>
                                    {field.answers_count === 0
                                        ? 'It has no answers.'
                                        : `${field.answers_count} ${field.answers_count === 1 ? 'answer is' : 'answers are'} deleted.`}
                                </DialogDescription>

                                <Form
                                    {...CustomFieldController.destroy.form(
                                        field.id,
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
                                                Delete field
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
