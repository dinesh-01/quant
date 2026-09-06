import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import UserFormFields from '@/components/users/user-form-fields';
import { create, index } from '@/routes/users';
import type { GlobalRole } from '@/types/user';

type CreateUserProps = {
    roles: GlobalRole[];
    can: { assignRoles: boolean };
};

export default function CreateUser({ roles, can }: CreateUserProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Users', href: index() },
            { title: 'New user', href: create() },
        ],
    });

    return (
        <>
            <Head title="New user" />

            <div className="max-w-2xl space-y-8 p-4">
                <Heading
                    title="New user"
                    description="They will receive a password reset link. Until outbound mail is configured, that link is written to the log."
                />

                <Form
                    {...UserController.store.form()}
                    disableWhileProcessing
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <UserFormFields
                                errors={errors}
                                roles={roles}
                                canAssignRoles={can.assignRoles}
                            />

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    Create user
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
