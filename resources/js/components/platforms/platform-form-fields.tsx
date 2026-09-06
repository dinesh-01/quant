import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PlatformFormFieldsProps = {
    errors: Partial<Record<string, string>>;
    defaults?: {
        name: string;
        notes: string | null;
        enable_on_design: boolean;
        enable_on_execution: boolean;
        is_open: boolean;
    };
};

export default function PlatformFormFields({
    errors,
    defaults,
}: PlatformFormFieldsProps) {
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
                    placeholder="Chrome"
                />

                <p className="text-muted-foreground text-xs">
                    Unique within this project. An environment the project
                    designs or executes against — Chrome, iOS, API.
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
                    placeholder="When this platform should be used."
                />

                <InputError message={errors.notes} />
            </div>

            <div className="flex items-start gap-3">
                <Checkbox
                    id="enable_on_design"
                    name="enable_on_design"
                    defaultChecked={defaults?.enable_on_design ?? true}
                />

                <div className="grid gap-1">
                    <Label htmlFor="enable_on_design">Available on design</Label>
                    <p className="text-muted-foreground text-xs">
                        Versions can be tagged with this platform on the
                        specification screen.
                    </p>
                </div>
            </div>

            <div className="flex items-start gap-3">
                <Checkbox
                    id="enable_on_execution"
                    name="enable_on_execution"
                    defaultChecked={defaults?.enable_on_execution ?? true}
                />

                <div className="grid gap-1">
                    <Label htmlFor="enable_on_execution">
                        Available on execution
                    </Label>
                    <p className="text-muted-foreground text-xs">
                        Plans can include this platform as an environment they
                        run against.
                    </p>
                </div>
            </div>

            <div className="flex items-start gap-3">
                <Checkbox
                    id="is_open"
                    name="is_open"
                    defaultChecked={defaults?.is_open ?? true}
                />

                <div className="grid gap-1">
                    <Label htmlFor="is_open">Open</Label>
                    <p className="text-muted-foreground text-xs">
                        Closing stops new assignments. Existing plan and version
                        tags stay until they are removed.
                    </p>
                </div>
            </div>
        </>
    );
}
