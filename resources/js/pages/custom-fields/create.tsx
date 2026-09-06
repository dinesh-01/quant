import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import CustomFieldController from '@/actions/App/Http/Controllers/CustomFields/CustomFieldController';
import CustomFieldFormFields from '@/components/custom-fields/custom-field-form-fields';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/custom-fields';
import type {
    CustomFieldEntityOption,
    CustomFieldTypeOption,
} from '@/types/custom-field';

type CreateCustomFieldProps = {
    types: CustomFieldTypeOption[];
    entities: CustomFieldEntityOption[];
};

export default function CreateCustomField({
    types,
    entities,
}: CreateCustomFieldProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Custom fields', href: index() },
            { title: 'New field', href: create() },
        ],
    });

    return (
        <>
            <Head title="New custom field" />

            <div className="max-w-2xl space-y-6 p-4">
                <Heading
                    title="New custom field"
                    description="Defined once here, then enabled per project."
                />

                <Form
                    {...CustomFieldController.store.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <CustomFieldFormFields
                                errors={errors}
                                types={types}
                                entities={entities}
                            />

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    Create field
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
