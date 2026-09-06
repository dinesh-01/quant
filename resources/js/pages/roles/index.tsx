import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/roles';
import type { RoleSummary } from '@/types/role';

type RolesIndexProps = {
    roles: RoleSummary[];
};

export default function RolesIndex({ roles }: RolesIndexProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Roles', href: index() }],
    });

    return (
        <>
            <Head title="Roles" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Roles"
                        description="What each role grants, and how many people are relying on it."
                    />

                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            New role
                        </Link>
                    </Button>
                </div>

                <ul className="divide-y rounded-lg border">
                    {roles.map((role) => (
                        <li
                            key={role.id}
                            className="flex flex-wrap items-center justify-between gap-4 p-4"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="truncate font-medium">
                                        {role.name}
                                    </p>

                                    {role.is_super_admin && (
                                        <Badge variant="destructive">
                                            Unrestricted
                                        </Badge>
                                    )}

                                    {role.is_default && (
                                        <Badge variant="secondary">
                                            Default for new accounts
                                        </Badge>
                                    )}
                                </div>

                                {role.description !== null && (
                                    <p className="text-muted-foreground truncate text-sm">
                                        {role.description}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-4">
                                <div className="text-muted-foreground text-right text-sm">
                                    <p>
                                        {role.is_super_admin
                                            ? 'Every ability'
                                            : `${role.abilities_count} abilities`}
                                    </p>

                                    <p className="text-xs">
                                        {role.users_count} global,{' '}
                                        {role.assignments_count} scoped
                                    </p>
                                </div>

                                <Button asChild variant="ghost" size="sm">
                                    <Link
                                        href={edit(role.id)}
                                        aria-label={`Edit ${role.name}`}
                                    >
                                        <Pencil className="size-4" />
                                    </Link>
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}
