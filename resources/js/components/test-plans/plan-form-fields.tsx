import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PlanFormFieldsProps = {
    errors: Partial<Record<string, string>>;
    defaults?: {
        name: string;
        description: string | null;
        is_active: boolean;
        is_open: boolean;
        is_public: boolean;
        status?: string;
    };
};

export default function PlanFormFields({
    errors,
    defaults,
}: PlanFormFieldsProps) {
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
                    placeholder="Release 4.2 regression"
                />

                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>

                <textarea
                    id="description"
                    name="description"
                    defaultValue={defaults?.description ?? ''}
                    rows={4}
                    className="border-input focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                    placeholder="What this round of testing covers."
                />

                <InputError message={errors.description} />
            </div>

            <div className="flex items-start gap-3">
                <Checkbox
                    id="is_open"
                    name="is_open"
                    defaultChecked={defaults?.is_open ?? true}
                />

                <div className="grid gap-1">
                    <Label htmlFor="is_open">Open for execution</Label>
                    <p className="text-muted-foreground text-xs">
                        Closing a plan keeps its results readable but stops
                        anything new being recorded against it. This is the
                        reversible way to retire a finished round of testing.
                    </p>
                </div>
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
                        Anyone who can reach the project can reach this plan.
                        Turn this off to restrict it to users given a role on
                        the plan itself.
                    </p>
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="status">Status</Label>
                <select
                    id="status"
                    name="status"
                    defaultValue={defaults?.status ?? 'active'}
                    className="border-input h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                >
                    <option value="active">Active</option>
                    <option value="draft">Draft</option>
                    <option value="archived">Archived</option>
                </select>
                <p className="text-muted-foreground text-xs">
                    Drafts are not offered for execution. Archived plans stay
                    intact but leave the default listing.
                </p>
                <InputError message={errors.status} />
            </div>
        </>
    );
}
