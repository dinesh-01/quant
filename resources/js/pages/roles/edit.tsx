import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/Roles/RoleController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import RoleFormFields from '@/components/roles/role-form-fields';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { edit, index } from '@/routes/roles';
import type { AbilityGroup, RoleFormValues } from '@/types/role';

type EditRoleProps = {
    role: RoleFormValues;
    abilityGroups: AbilityGroup[];
};

export default function EditRole({ role, abilityGroups }: EditRoleProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Roles', href: index() },
            { title: role.name, href: edit(role.id) },
        ],
    });

    const inUse = role.users_count + role.assignments_count;

    return (
        <>
            <Head title={`Edit ${role.name}`} />

            <div className="max-w-3xl space-y-8 p-4">
                <Heading
                    title={`Edit ${role.name}`}
                    description="Changes apply to everyone holding this role, everywhere, as soon as they are saved."
                />

                {inUse > 0 && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-200/10 dark:bg-amber-700/10">
                        <p>
                            {role.users_count} account
                            {role.users_count === 1 ? '' : 's'} hold this as
                            their global role, and it is assigned{' '}
                            {role.assignments_count} time
                            {role.assignments_count === 1 ? '' : 's'} to
                            specific projects or plans.
                            {role.is_own_role &&
                                ' This is your own global role, so removing an ability removes it from you too.'}
                        </p>
                    </div>
                )}

                <Form
                    {...RoleController.update.form(role.id)}
                    disableWhileProcessing
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <RoleFormFields
                                errors={errors}
                                abilityGroups={abilityGroups}
                                defaults={role}
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
                        title="Delete role"
                        description={
                            inUse > 0
                                ? 'Not available while anyone holds this role.'
                                : 'Nobody holds this role, so removing it changes no one’s access.'
                        }
                    />

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button variant="destructive" disabled={inUse > 0}>
                                Delete role
                            </Button>
                        </DialogTrigger>

                        <DialogContent>
                            <DialogTitle>Delete {role.name}?</DialogTitle>

                            <DialogDescription>
                                Type the role name to confirm. What it granted
                                is not recorded anywhere else, so recreating it
                                means reconstructing it from memory.
                            </DialogDescription>

                            <Form
                                {...RoleController.destroy.form(role.id)}
                                className="space-y-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="confirm_name">
                                                Role name
                                            </Label>

                                            <Input
                                                id="confirm_name"
                                                name="confirm_name"
                                                autoComplete="off"
                                                placeholder={role.name}
                                            />

                                            <InputError
                                                message={errors.confirm_name}
                                            />
                                        </div>

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
                                                Delete role
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </>
    );
}
