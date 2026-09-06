import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type BuildFormFieldsProps = {
    errors: Partial<Record<string, string>>;
    defaults?: {
        name: string;
        notes: string | null;
        is_active: boolean;
        is_open: boolean;
        release_date: string | null;
    };
};

export default function BuildFormFields({
    errors,
    defaults,
}: BuildFormFieldsProps) {
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
                    maxLength={100}
                    placeholder="1.4.2"
                />

                <p className="text-muted-foreground text-xs">
                    Unique within this plan. A named snapshot of the software
                    the plan is executed against.
                </p>

                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="notes">Notes</Label>

                <textarea
                    id="notes"
                    name="notes"
                    defaultValue={defaults?.notes ?? ''}
                    rows={3}
                    className="border-input focus-visible:border-ring focus-visible:ring-ring/50 min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                    placeholder="What this build contains."
                />

                <InputError message={errors.notes} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="release_date">Release date</Label>

                <Input
                    id="release_date"
                    name="release_date"
                    type="date"
                    defaultValue={defaults?.release_date ?? ''}
                />

                <InputError message={errors.release_date} />
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
                        Closing stops new results being recorded against this
                        build. It stays listed.
                    </p>
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
                        Inactive builds stay intact but are left out of
                        listings.
                    </p>
                </div>
            </div>
        </>
    );
}
