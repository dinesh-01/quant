import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import UserFormFields from '@/components/users/user-form-fields';
import { edit, index } from '@/routes/users';
import type { GlobalRole, UserFormValues } from '@/types/user';

type EditUserProps = {
    user: UserFormValues;
    roles: GlobalRole[];
    can: { assignRoles: boolean };
};

export default function EditUser({ user, roles, can }: EditUserProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Users', href: index() },
            { title: user.name, href: edit(user.id) },
        ],
    });

    return (
        <>
            <Head title={`Edit ${user.name}`} />

            <div className="max-w-2xl space-y-8 p-4">
                <Heading
                    title={`Edit ${user.name}`}
                    description="Account details, access dates and the global role."
                />

                <Form
                    {...UserController.update.form(user.id)}
                    disableWhileProcessing
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <UserFormFields
                                errors={errors}
                                roles={roles}
                                defaults={user}
                                canAssignRoles={can.assignRoles}
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

                {can.assignRoles && !user.is_self && (
                    <>
                        <Separator />

                        <div className="space-y-4">
                            <Heading
                                variant="small"
                                title="Email a reset link"
                                description="They choose a new password. Their other sessions are signed out. Until SMTP is configured the link is written to the application log."
                            />

                            <Form
                                {...UserController.sendPasswordResetLink.form(
                                    user.id,
                                )}
                                disableWhileProcessing
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Send reset link
                                    </Button>
                                )}
                            </Form>
                        </div>

                        <div className="space-y-4">
                            <Heading
                                variant="small"
                                title="Set a new password"
                                description="Fallback when they cannot reach email. Their other sessions are signed out and any remembered device stops working."
                            />

                            <Form
                                {...UserController.updatePassword.form(user.id)}
                                disableWhileProcessing
                                resetOnSuccess
                                className="max-w-sm space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="password">
                                                New password
                                            </Label>

                                            <Input
                                                id="password"
                                                name="password"
                                                type="password"
                                                required
                                                autoComplete="new-password"
                                            />

                                            <Input
                                                name="password_confirmation"
                                                type="password"
                                                required
                                                autoComplete="new-password"
                                                aria-label="Confirm new password"
                                                placeholder="Confirm password"
                                            />

                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            variant="secondary"
                                            disabled={processing}
                                        >
                                            Set password
                                        </Button>
                                    </>
                                )}
                            </Form>

                            <p className="text-muted-foreground text-xs">
                                Tell them out of band, and ask them to change it
                                once they are in.
                            </p>
                        </div>
                    </>
                )}

                <Separator />

                <p className="text-muted-foreground text-sm">
                    Accounts are never deleted. Test case versions and test runs
                    are attributed to whoever made them, so deactivating is how
                    access is withdrawn without losing that record.
                </p>
            </div>
        </>
    );
}
