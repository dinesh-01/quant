import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Pencil, Search, UserPlus } from 'lucide-react';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index } from '@/routes/users';
import type { Paginated } from '@/types/pagination';
import type { UserRow } from '@/types/user';

type UsersIndexProps = {
    users: Paginated<UserRow>;
    search: string;
};

export default function UsersIndex({ users, search }: UsersIndexProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Users', href: index() }],
    });

    return (
        <>
            <Head title="Users" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Users"
                        description="Accounts that can sign in, and the role each one holds by default."
                    />

                    <Button asChild>
                        <Link href={create()}>
                            <UserPlus className="size-4" />
                            New user
                        </Link>
                    </Button>
                </div>

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
                    <ul className="divide-y rounded-lg border">
                        {users.data.map((user) => (
                            <li
                                key={user.id}
                                className="flex flex-wrap items-center justify-between gap-4 p-4"
                            >
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="truncate font-medium">
                                            {user.name}
                                        </p>

                                        {!user.is_active && (
                                            <Badge variant="destructive">
                                                Deactivated
                                            </Badge>
                                        )}

                                        {user.is_active && !user.is_usable && (
                                            <Badge variant="destructive">
                                                Expired
                                            </Badge>
                                        )}
                                    </div>

                                    <p className="text-muted-foreground truncate text-sm">
                                        {user.email}
                                    </p>
                                </div>

                                <div className="flex items-center gap-4">
                                    <div className="text-right text-sm">
                                        <p>
                                            {user.role_name ?? (
                                                <span className="text-muted-foreground">
                                                    No global role
                                                </span>
                                            )}
                                        </p>

                                        {user.expires_at !== null && (
                                            <p className="text-muted-foreground text-xs">
                                                Expires {user.expires_at}
                                            </p>
                                        )}
                                    </div>

                                    <Button asChild variant="ghost" size="sm">
                                        <Link
                                            href={edit(user.id)}
                                            aria-label={`Edit ${user.name}`}
                                        >
                                            <Pencil className="size-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
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
