import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/Roles/RoleController';
import Heading from '@/components/heading';
import RoleFormFields from '@/components/roles/role-form-fields';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/roles';
import type { AbilityGroup } from '@/types/role';

type CreateRoleProps = {
    abilityGroups: AbilityGroup[];
};

export default function CreateRole({ abilityGroups }: CreateRoleProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Roles', href: index() },
            { title: 'New role', href: create() },
        ],
    });

    return (
        <>
            <Head title="New role" />

            <div className="max-w-3xl space-y-8 p-4">
                <Heading
                    title="New role"
                    description="Name the role, then tick what it grants."
                />

                <Form
                    {...RoleController.store.form()}
                    disableWhileProcessing
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <RoleFormFields
                                errors={errors}
                                abilityGroups={abilityGroups}
                            />

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    Create role
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
