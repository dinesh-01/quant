import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type KeywordFormFieldsProps = {
    errors: Partial<Record<string, string>>;
    defaults?: {
        name: string;
        notes: string | null;
    };
};

export default function KeywordFormFields({
    errors,
    defaults,
}: KeywordFormFieldsProps) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="name">Keyword</Label>

                <Input
                    id="name"
                    name="name"
                    defaultValue={defaults?.name}
                    required
                    autoFocus
                    maxLength={100}
                    placeholder="regression"
                />

                <p className="text-muted-foreground text-xs">
                    Unique within this project, ignoring capitalisation.
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
                    placeholder="What this keyword is for, so the next person tags the same cases with it."
                />

                <InputError message={errors.notes} />
            </div>
        </>
    );
}
