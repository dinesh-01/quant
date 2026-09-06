import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { GlobalRole, UserFormValues } from '@/types/user';

type UserFormFieldsProps = {
    errors: Partial<Record<string, string>>;
    roles: GlobalRole[];
    defaults?: Omit<UserFormValues, 'id'>;
    /**
     * False when the actor holds `manage_users` but not `assign_global_roles`.
     * The role field is left out entirely rather than disabled, because a
     * disabled field still submits nothing and would read as "no role".
     */
    canAssignRoles: boolean;
    /** True on the create form, where an initial password is required. */
    requirePassword?: boolean;
};

export default function UserFormFields({
    errors,
    roles,
    defaults,
    canAssignRoles,
    requirePassword = false,
}: UserFormFieldsProps) {
    const isSelf = defaults?.is_self ?? false;

    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>

                <Input
                    id="name"
                    name="name"
                    defaultValue={defaults?.name}
                    required
                    autoFocus
                    autoComplete="off"
                    placeholder="Ada Lovelace"
                />

                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">Email address</Label>

                <Input
                    id="email"
                    name="email"
                    type="email"
                    defaultValue={defaults?.email}
                    required
                    autoComplete="off"
                    placeholder="ada@example.com"
                />

                <InputError message={errors.email} />
            </div>

            {requirePassword && (
                <div className="grid gap-2">
                    <Label htmlFor="password">Initial password</Label>

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
                        aria-label="Confirm initial password"
                        placeholder="Confirm password"
                    />

                    <p className="text-muted-foreground text-xs">
                        Tell the new user this password out of band, and ask
                        them to change it once they are in.
                    </p>

                    <InputError message={errors.password} />
                </div>
            )}

            {canAssignRoles && (
                <div className="grid gap-2">
                    <Label htmlFor="role_id">Global role</Label>

                    <select
                        id="role_id"
                        name="role_id"
                        defaultValue={defaults?.role_id ?? ''}
                        className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                    >
                        <option value="">No global role</option>

                        {roles.map((role) => (
                            <option key={role.id} value={role.id}>
                                {role.name}
                                {role.is_super_admin ? ' — unrestricted' : ''}
                            </option>
                        ))}
                    </select>

                    <p className="text-muted-foreground text-xs">
                        Applies wherever this user holds no role on a specific
                        project or plan. Without one they can reach only the
                        projects they are named on.
                    </p>

                    <InputError message={errors.role_id} />
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor="expires_at">Access expires</Label>

                <Input
                    id="expires_at"
                    name="expires_at"
                    type="date"
                    defaultValue={defaults?.expires_at ?? ''}
                    className="w-fit"
                />

                <p className="text-muted-foreground text-xs">
                    Leave empty for no expiry. Access lasts to the end of the
                    day given, so today still works today.
                </p>

                <InputError message={errors.expires_at} />
            </div>

            <div className="flex items-start gap-3">
                <Checkbox
                    id="is_active"
                    name="is_active"
                    defaultChecked={defaults?.is_active ?? true}
                />

                <div className="grid gap-1">
                    <Label htmlFor="is_active">Active</Label>
                    <p className="text-muted-foreground text-xs">
                        {isSelf
                            ? 'This is your own account. Switching it off would end your session on the next request, so it is refused — ask another administrator.'
                            : 'Turning this off ends the session immediately and refuses every sign-in, while keeping the account and its history.'}
                    </p>
                </div>
            </div>

            <InputError message={errors.is_active} />
        </>
    );
}
