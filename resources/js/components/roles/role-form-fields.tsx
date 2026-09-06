import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import type { AbilityGroup, RoleFormValues } from '@/types/role';

type RoleFormFieldsProps = {
    errors: Partial<Record<string, string>>;
    abilityGroups: AbilityGroup[];
    defaults?: Omit<RoleFormValues, 'id'>;
};

export default function RoleFormFields({
    errors,
    abilityGroups,
    defaults,
}: RoleFormFieldsProps) {
    const granted = new Set(defaults?.abilities ?? []);
    const isSuperAdmin = defaults?.is_super_admin ?? false;

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
                    placeholder="Senior Tester"
                />

                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>

                <Input
                    id="description"
                    name="description"
                    defaultValue={defaults?.description ?? ''}
                    placeholder="What someone with this role is expected to do."
                />

                <InputError message={errors.description} />
            </div>

            <div className="flex items-start gap-3">
                <Checkbox
                    id="is_default"
                    name="is_default"
                    defaultChecked={defaults?.is_default ?? false}
                />

                <div className="grid gap-1">
                    <Label htmlFor="is_default">
                        Give this role to new accounts
                    </Label>
                    <p className="text-muted-foreground text-xs">
                        Exactly one role is the default. Turning this on takes
                        it off whichever role holds it now.
                    </p>
                </div>
            </div>

            <InputError message={errors.is_default} />

            <div className="flex items-start gap-3">
                <Checkbox
                    id="is_super_admin"
                    name="is_super_admin"
                    defaultChecked={isSuperAdmin}
                />

                <div className="grid gap-1">
                    <Label htmlFor="is_super_admin">Unrestricted</Label>
                    <p className="text-muted-foreground text-xs">
                        Passes every check in every project regardless of what
                        is ticked below, and only when held as a global role.
                        Assigning it to a single project or plan does nothing.
                    </p>
                </div>
            </div>

            <Separator />

            <div className="space-y-2">
                <Label>Abilities</Label>

                <p className="text-muted-foreground text-sm">
                    {isSuperAdmin
                        ? 'Ignored while this role is unrestricted, but kept, so the grants return if that is turned off.'
                        : 'A role given for one project replaces the global role there rather than adding to it, so it needs everything its holders should have in that project.'}
                </p>

                <InputError message={errors.abilities} />
            </div>

            <div className="space-y-6">
                {abilityGroups.map((group) => (
                    <fieldset key={group.name} className="space-y-3">
                        <legend className="text-sm font-medium">
                            {group.name}
                        </legend>

                        <div className="grid gap-3 sm:grid-cols-2">
                            {group.abilities.map((ability) => (
                                <div
                                    key={ability.value}
                                    className="flex items-start gap-3"
                                >
                                    <Checkbox
                                        id={ability.value}
                                        name="abilities[]"
                                        value={ability.value}
                                        defaultChecked={granted.has(
                                            ability.value,
                                        )}
                                    />

                                    <div className="grid gap-1">
                                        <Label
                                            htmlFor={ability.value}
                                            className="font-normal"
                                        >
                                            {ability.label}
                                        </Label>

                                        {ability.is_system && (
                                            <Badge
                                                variant="outline"
                                                className="w-fit text-xs font-normal"
                                            >
                                                Global role only
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </fieldset>
                ))}
            </div>
        </>
    );
}
