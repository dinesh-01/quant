import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Pencil, Search, UserPlus } from 'lucide-react';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
import { PageHead } from '@/components/chrome/page-head';
import { StatusPill, UserAvatar } from '@/components/chrome/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index } from '@/routes/users';
import type { Paginated } from '@/types/pagination';
import type { UserRow } from '@/types/user';

type UsersIndexProps = {
    users: Paginated<UserRow>;
    search: string;
};

const roleClass: Record<string, string> = {
    Admin: 'bg-primary-50 text-primary-700',
    'Team Leader': 'bg-info-bg text-info',
    'Senior Tester': 'bg-success-bg text-success',
    Tester: 'bg-neutral-bg text-neutral',
    'Test Designer': 'bg-[#f3e9fb] text-[#9333ea]',
    Guest: 'bg-warning-bg text-warning',
};

export default function UsersIndex({ users, search }: UsersIndexProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Users', href: index() }],
    });

    return (
        <>
            <Head title="Users" />

            <div className="space-y-5 p-6">
                <PageHead
                    title="Users"
                    description="People with access to this workspace and their project-scoped roles."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <UserPlus />
                                Invite user
                            </Link>
                        </Button>
                    }
                />

                <Form
                    {...UserController.index.form()}
                    className="flex max-w-md items-center gap-2"
                >
                    <Input
                        name="search"
                        defaultValue={search}
                        placeholder="Search by name or email"
                        aria-label="Search users"
                    />
                    <Button type="submit" variant="secondary">
                        <Search className="size-4" />
                        Search
                    </Button>
                </Form>

                {users.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-12 text-center">
                        <p className="text-muted-foreground text-sm">
                            {search === ''
                                ? 'No accounts yet.'
                                : `No account matches "${search}".`}
                        </p>
                    </div>
                ) : (
                    <div className="bg-card overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                        <table className="w-full text-[13.5px]">
                            <thead className="bg-muted text-muted-foreground text-left text-xs font-semibold tracking-[0.04em] uppercase">
                                <tr>
                                    <th className="px-4 py-2.5">User</th>
                                    <th className="px-4 py-2.5">Role</th>
                                    <th className="px-4 py-2.5">Status</th>
                                    <th className="px-4 py-2.5" />
                                </tr>
                            </thead>
                            <tbody>
                                {users.data.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="border-border border-t"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2.5">
                                                <UserAvatar name={user.name} />
                                                <div>
                                                    <div className="font-semibold">
                                                        {user.name}
                                                    </div>
                                                    <div className="text-muted-foreground text-xs">
                                                        {user.email}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ${roleClass[user.role_name ?? ''] ?? 'bg-neutral-bg text-neutral'}`}
                                            >
                                                {user.role_name ?? 'No global role'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            {!user.is_active ? (
                                                <StatusPill
                                                    status="deactivated"
                                                    label="Deactivated"
                                                />
                                            ) : !user.is_usable ? (
                                                <StatusPill
                                                    status="blocked"
                                                    label="Expired"
                                                />
                                            ) : (
                                                <StatusPill
                                                    status="active"
                                                    label="Active"
                                                />
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link
                                                    href={edit(user.id)}
                                                    aria-label={`Edit ${user.name}`}
                                                >
                                                    <Pencil className="size-4" />
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {users.last_page > 1 && (
                    <div className="flex items-center justify-between gap-4">
                        <p className="text-muted-foreground text-sm">
                            {users.from}–{users.to} of {users.total}
                        </p>
                        <div className="flex gap-2">
                            <Button
                                asChild={users.prev_page_url !== null}
                                variant="secondary"
                                size="sm"
                                disabled={users.prev_page_url === null}
                            >
                                {users.prev_page_url === null ? (
                                    <span>Previous</span>
                                ) : (
                                    <Link href={users.prev_page_url}>
                                        Previous
                                    </Link>
                                )}
                            </Button>
                            <Button
                                asChild={users.next_page_url !== null}
                                variant="secondary"
                                size="sm"
                                disabled={users.next_page_url === null}
                            >
                                {users.next_page_url === null ? (
                                    <span>Next</span>
                                ) : (
                                    <Link href={users.next_page_url}>Next</Link>
                                )}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
