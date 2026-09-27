import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Check, Plus, Shield } from 'lucide-react';
import { PageHead } from '@/components/chrome/page-head';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/roles';
import type { AbilityGroup, RoleSummary } from '@/types/role';
import { cn } from '@/lib/utils';

type RolesIndexProps = {
    roles: RoleSummary[];
    abilityGroups: AbilityGroup[];
};

const roleTone: Record<string, string> = {
    Admin: 'bg-primary-50 text-primary-700',
    'Team Leader': 'bg-info-bg text-info',
    'Senior Tester': 'bg-success-bg text-success',
    Tester: 'bg-neutral-bg text-neutral',
    'Test Designer': 'bg-[#f3e9fb] text-[#9333ea]',
    Guest: 'bg-warning-bg text-warning',
};

function Switch({ on }: { on: boolean }) {
    return (
        <span
            className={cn(
                'relative inline-flex h-[22px] w-[38px] shrink-0 rounded-full transition-colors',
                on ? 'bg-primary' : 'bg-border-strong',
            )}
            aria-hidden
        >
            <span
                className={cn(
                    'absolute top-0.5 size-[18px] rounded-full bg-white shadow-sm transition-[left]',
                    on ? 'left-[18px]' : 'left-0.5',
                )}
            />
        </span>
    );
}

export default function RolesIndex({
    roles,
    abilityGroups = [],
}: RolesIndexProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Roles', href: index() }],
    });

    const [selectedId, setSelectedId] = useState(roles[0]?.id ?? null);
    const selected =
        roles.find((role) => role.id === selectedId) ?? roles[0] ?? null;

    const granted = useMemo(
        () => new Set(selected?.abilities ?? []),
        [selected],
    );

    const matrixAbilities = useMemo(
        () =>
            abilityGroups.flatMap((group) =>
                group.abilities.map((ability) => ({
                    group: group.name,
                    ...ability,
                })),
            ),
        [abilityGroups],
    );

    return (
        <>
            <Head title="Roles" />

            <div className="space-y-5 p-6">
                <PageHead
                    title="Roles & abilities"
                    description="Define what each role can do. Abilities apply within the projects a user is assigned to."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus />
                                New role
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-[18px] lg:grid-cols-[268px_1fr]">
                    <div className="bg-card self-start overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                        <div className="flex items-center justify-between border-b px-3.5 py-3">
                            <h2 className="text-[13px] font-bold">Roles</h2>
                            <span className="text-text-subtle text-[11.5px]">
                                {roles.length}
                            </span>
                        </div>
                        <ul className="p-2">
                            {roles.map((role) => (
                                <li key={role.id}>
                                    <button
                                        type="button"
                                        onClick={() => setSelectedId(role.id)}
                                        className={cn(
                                            'flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-[13.5px]',
                                            selected?.id === role.id
                                                ? 'bg-primary-50 text-primary-700 font-semibold'
                                                : 'hover:bg-muted',
                                        )}
                                    >
                                        <Shield className="size-4 shrink-0" />
                                        <span className="truncate">
                                            {role.name}
                                        </span>
                                        <span className="text-text-subtle ml-auto text-[11.5px]">
                                            {role.users_count +
                                                role.assignments_count}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                        <div className="border-t p-3.5">
                            <Button asChild size="sm" variant="outline" className="w-full">
                                <Link href={create()}>
                                    <Plus />
                                    Add role
                                </Link>
                            </Button>
                        </div>
                    </div>

                    {selected && (
                        <div className="bg-card overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                            <div className="flex flex-wrap items-center gap-3 border-b px-[18px] py-4">
                                <span className="bg-primary-50 text-primary-700 flex size-[34px] items-center justify-center rounded-[10px]">
                                    <Shield className="size-[18px]" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <h2 className="text-[15px] font-bold">
                                        {selected.name}
                                    </h2>
                                    <p className="text-muted-foreground text-[12.5px]">
                                        {selected.description ??
                                            (selected.is_super_admin
                                                ? 'Full control over the workspace and all projects'
                                                : 'Custom role')}
                                    </p>
                                </div>
                                <span
                                    className={cn(
                                        'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                        roleTone[selected.name] ??
                                            'bg-neutral-bg text-neutral',
                                    )}
                                >
                                    {selected.users_count +
                                        selected.assignments_count}{' '}
                                    {selected.users_count +
                                        selected.assignments_count ===
                                    1
                                        ? 'member'
                                        : 'members'}
                                </span>
                                <Button asChild size="sm">
                                    <Link href={edit(selected.id)}>Edit</Link>
                                </Button>
                            </div>

                            {selected.is_super_admin && (
                                <div className="bg-primary-50 border-primary-100 text-primary-700 mx-[18px] mt-4 flex items-center gap-3 rounded-xl border px-4 py-3 text-[13px] font-medium">
                                    Admin is a system role — abilities are
                                    locked on. Duplicate it from Edit to create
                                    a customizable variant.
                                </div>
                            )}

                            <div className="p-[18px]">
                                {abilityGroups.map((group) => (
                                    <div key={group.name} className="mb-1.5">
                                        <p className="text-text-subtle pt-3 pb-1 text-[11px] font-bold tracking-[0.05em] uppercase">
                                            {group.name}
                                        </p>
                                        {group.abilities.map((ability) => {
                                            const on =
                                                selected.is_super_admin ||
                                                granted.has(ability.value);

                                            return (
                                                <div
                                                    key={ability.value}
                                                    className="border-border flex items-center gap-3 border-b py-2.5 last:border-0"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="text-[13.5px]">
                                                            {ability.label}
                                                        </p>
                                                        {ability.is_system && (
                                                            <p className="text-muted-foreground text-xs">
                                                                System ability —
                                                                only effective
                                                                as a global
                                                                role
                                                            </p>
                                                        )}
                                                    </div>
                                                    <span className="ml-auto">
                                                        <Switch on={on} />
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                <div className="bg-card overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                    <div className="border-b px-[18px] py-4">
                        <h2 className="text-[15px] font-bold">
                            Ability matrix
                        </h2>
                        <p className="text-muted-foreground text-[12.5px]">
                            Compare abilities across all roles
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-[13.5px]">
                            <thead className="bg-muted text-muted-foreground text-left text-xs font-semibold tracking-[0.04em] uppercase">
                                <tr>
                                    <th className="min-w-[220px] px-4 py-2.5">
                                        Ability
                                    </th>
                                    {roles.map((role) => (
                                        <th
                                            key={role.id}
                                            className="px-3 py-2.5 text-center"
                                        >
                                            {role.name}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {matrixAbilities.map((ability) => (
                                    <tr
                                        key={ability.value}
                                        className="border-border border-t"
                                    >
                                        <td className="px-4 py-3">
                                            {ability.label}
                                        </td>
                                        {roles.map((role) => {
                                            const on =
                                                role.is_super_admin ||
                                                role.abilities.includes(
                                                    ability.value,
                                                );

                                            return (
                                                <td
                                                    key={role.id}
                                                    className="px-3 py-3 text-center"
                                                >
                                                    <span
                                                        className={cn(
                                                            'inline-grid size-[22px] place-items-center rounded-md',
                                                            on
                                                                ? 'bg-success-bg text-success'
                                                                : 'bg-neutral-bg text-text-subtle',
                                                        )}
                                                    >
                                                        {on ? (
                                                            <Check className="size-3.5" />
                                                        ) : (
                                                            '·'
                                                        )}
                                                    </span>
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
