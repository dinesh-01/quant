import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ProjectFormFieldsProps = {
    errors: Partial<Record<string, string>>;
    defaults?: {
        name: string;
        prefix: string;
        description: string | null;
        is_active: boolean;
        is_public: boolean;
    };
    /**
     * Set once the project has issued its first PREFIX-N id. The server refuses
     * the change either way; this only explains why rather than letting the
     * user type a new prefix and be rejected.
     */
    prefixLocked?: boolean;
};

export default function ProjectFormFields({
    errors,
    defaults,
    prefixLocked = false,
}: ProjectFormFieldsProps) {
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
                    placeholder="Payments platform"
                />

                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="prefix">Test case prefix</Label>

                <Input
                    id="prefix"
                    name="prefix"
                    defaultValue={defaults?.prefix}
                    required
                    readOnly={prefixLocked}
                    maxLength={16}
                    className="font-mono uppercase"
                    placeholder="PAY"
                />

                <p className="text-muted-foreground text-xs">
                    {prefixLocked
                        ? 'Fixed: test cases have already been numbered with this prefix, and those ids are quoted outside this application.'
                        : 'Every test case is identified by this prefix and a number, like PAY-42. Letters, digits, dashes and underscores.'}
                </p>

                <InputError message={errors.prefix} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>

                <textarea
                    id="description"
                    name="description"
                    defaultValue={defaults?.description ?? ''}
                    rows={4}
                    className="border-input focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                    placeholder="What this project covers."
                />

                <InputError message={errors.description} />
            </div>

            <div className="flex items-start gap-3">
                <Checkbox
                    id="is_public"
                    name="is_public"
                    defaultChecked={defaults?.is_public ?? true}
                />

                <div className="grid gap-1">
                    <Label htmlFor="is_public">Open to everyone</Label>
                    <p className="text-muted-foreground text-xs">
                        Anyone signed in works in this project under their
                        global role. Turn this off to restrict it to users given
                        a role on this project specifically.
                    </p>

                    <InputError message={errors.is_public} />
                </div>
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
                        Inactive projects stay intact but disappear from the
                        project list for everyone except administrators.
                    </p>

                    <InputError message={errors.is_active} />
                </div>
            </div>
        </>
    );
}
