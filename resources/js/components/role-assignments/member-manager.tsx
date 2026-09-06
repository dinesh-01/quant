import { Form } from '@inertiajs/react';
import { Trash2, UserPlus } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AssignableRole, ScopeMember } from '@/types/role-assignment';
import type { RouteFormDefinition } from '@/wayfinder';

type MemberManagerProps = {
    members: ScopeMember[];
    roles: AssignableRole[];
    /** True when the scope is private, so removing a role removes all access. */
    isRestricted: boolean;
    scopeNoun: 'project' | 'plan';
    /** Wayfinder form definitions, so the pages own the route arguments. */
    assignForm: () => RouteFormDefinition<'post'>;
    revokeForm: (userId: number) => RouteFormDefinition<'post'>;
    emptyMessage: string;
};

/**
 * The member list for a project or a plan.
 *
 * Assigning and changing a role are the same operation — one role per user per
 * scope — so a row's role dropdown submits the same endpoint as the add form.
 */
export default function MemberManager({
    members,
    roles,
    isRestricted,
    scopeNoun,
    assignForm,
    revokeForm,
    emptyMessage,
}: MemberManagerProps) {
    return (
        <div className="space-y-8">
            <div className="space-y-4">
                <Heading
                    variant="small"
                    title={`Add someone to this ${scopeNoun}`}
                    description={
                        isRestricted
                            ? `This ${scopeNoun} is private, so only the people listed here can reach it.`
                            : `This ${scopeNoun} is open to everyone, so a role here replaces someone's global role rather than granting access they lacked.`
                    }
                />

                <Form
                    {...assignForm()}
                    resetOnSuccess
                    className="flex flex-wrap items-start gap-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid min-w-64 flex-1 gap-2">
                                <Label htmlFor="user_email">
                                    Email address
                                </Label>

                                <Input
                                    id="user_email"
                                    name="user_email"
                                    type="email"
                                    required
                                    placeholder="tester@example.com"
                                />

                                <InputError message={errors.user_email} />
                            </div>

                            <div className="grid min-w-48 gap-2">
                                <Label htmlFor="role_id">Role</Label>

                                <select
                                    id="role_id"
                                    name="role_id"
                                    required
                                    defaultValue=""
                                    className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                >
                                    <option value="" disabled>
                                        Choose a role
                                    </option>

                                    {roles.map((role) => (
                                        <option key={role.id} value={role.id}>
                                            {role.name}
                                        </option>
                                    ))}
                                </select>

                                <InputError message={errors.role_id} />
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="mt-6"
                            >
                                <UserPlus className="size-4" />
                                Add
                            </Button>
                        </>
                    )}
                </Form>
            </div>

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Current members"
                    description={`Everyone holding a role for this ${scopeNoun} specifically.`}
                />

                {members.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-center">
                        <p className="text-muted-foreground text-sm">
                            {emptyMessage}
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {members.map((member) => (
                            <li
                                key={member.id}
                                className="flex flex-wrap items-center justify-between gap-4 p-4"
                            >
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <p className="truncate font-medium">
                                            {member.name}
                                        </p>

                                        {member.is_self && (
                                            <Badge variant="outline">You</Badge>
                                        )}
                                    </div>

                                    <p className="text-muted-foreground truncate text-sm">
                                        {member.email}
                                    </p>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Form {...assignForm()}>
                                        {({ submit }) => (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="user_email"
                                                    value={member.email}
                                                />

                                                <select
                                                    name="role_id"
                                                    defaultValue={
                                                        member.role_id
                                                    }
                                                    onChange={() => submit()}
                                                    aria-label={`Role for ${member.name}`}
                                                    className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                                >
                                                    {roles.every(
                                                        (role) =>
                                                            role.id !==
                                                            member.role_id,
                                                    ) && (
                                                        <option
                                                            value={
                                                                member.role_id
                                                            }
                                                        >
                                                            {member.role_name}
                                                        </option>
                                                    )}

                                                    {roles.map((role) => (
                                                        <option
                                                            key={role.id}
                                                            value={role.id}
                                                        >
                                                            {role.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </>
                                        )}
                                    </Form>

                                    <Form {...revokeForm(member.id)}>
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="ghost"
                                                size="sm"
                                                disabled={processing}
                                                aria-label={`Remove ${member.name}`}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
