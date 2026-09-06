import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    CustomFieldEntityOption,
    CustomFieldFormValues,
    CustomFieldTypeOption,
} from '@/types/custom-field';

type CustomFieldFormFieldsProps = {
    errors: Partial<Record<string, string>>;
    types: CustomFieldTypeOption[];
    entities: CustomFieldEntityOption[];
    defaults?: CustomFieldFormValues;
};

const selectClasses =
    'border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:opacity-50';

/**
 * The definition form.
 *
 * The chosen type decides which of the remaining inputs mean anything, so it is
 * the one piece of state here: a dropdown has values to choose from and no
 * pattern, a numeric field has neither. Hiding what does not apply keeps the
 * form honest — legacy showed every input for every type and then ignored most
 * of them, which is how `valid_regexp` came to be filled in on fields that
 * never consulted it.
 */
export default function CustomFieldFormFields({
    errors,
    types,
    entities,
    defaults,
}: CustomFieldFormFieldsProps) {
    const [type, setType] = useState(defaults?.type ?? types[0].value);
    const [options, setOptions] = useState<string[]>(defaults?.options ?? ['']);

    const chosen = types.find((candidate) => candidate.value === type);
    const frozen = defaults?.is_answered ?? false;

    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="label">Label</Label>

                <Input
                    id="label"
                    name="label"
                    defaultValue={defaults?.label}
                    required
                    autoFocus
                    maxLength={64}
                    placeholder="Browser"
                />

                <p className="text-muted-foreground text-xs">
                    What people filling the field in will read.
                </p>

                <InputError message={errors.label} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>

                <Input
                    id="name"
                    name="name"
                    defaultValue={defaults?.name}
                    required
                    maxLength={64}
                    placeholder="browser"
                />

                <p className="text-muted-foreground text-xs">
                    Letters, numbers, underscores and hyphens. This is the
                    identifier that travels with exported values, so it is worth
                    keeping stable.
                </p>

                <InputError message={errors.name} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="entity_type">Recorded against</Label>

                    <select
                        id="entity_type"
                        name="entity_type"
                        defaultValue={defaults?.entity_type}
                        disabled={frozen}
                        className={selectClasses}
                    >
                        {entities.map((entity) => (
                            <option key={entity.value} value={entity.value}>
                                {entity.label}
                            </option>
                        ))}
                    </select>

                    <InputError message={errors.entity_type} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="type">Type</Label>

                    <select
                        id="type"
                        name="type"
                        value={type}
                        onChange={(event) =>
                            setType(
                                event.target
                                    .value as CustomFieldFormValues['type'],
                            )
                        }
                        disabled={frozen}
                        className={selectClasses}
                    >
                        {types.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>

                    <InputError message={errors.type} />
                </div>
            </div>

            {frozen && (
                <>
                    {/* A disabled control posts nothing, and both are required. */}
                    <input
                        type="hidden"
                        name="entity_type"
                        value={defaults?.entity_type}
                    />
                    <input type="hidden" name="type" value={type} />
                </>
            )}

            {frozen && (
                <p className="text-muted-foreground text-xs">
                    The type and what it is recorded against are fixed now that
                    answers exist: they were checked against this definition as
                    it reads today, and reinterpreting them under another type
                    would be a guess. Everything else is still editable.
                </p>
            )}

            {chosen?.has_options && (
                <div className="grid gap-2">
                    <Label>Values to choose from</Label>

                    {options.map((option, index) => (
                        <div key={index} className="flex gap-2">
                            <Input
                                name="options[]"
                                value={option}
                                onChange={(event) =>
                                    setOptions(
                                        options.map((current, position) =>
                                            position === index
                                                ? event.target.value
                                                : current,
                                        ),
                                    )
                                }
                                placeholder="Chrome"
                            />

                            {options.length > 1 && (
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    onClick={() =>
                                        setOptions(
                                            options.filter(
                                                (_, position) =>
                                                    position !== index,
                                            ),
                                        )
                                    }
                                    aria-label={`Remove ${option || 'value'}`}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            )}
                        </div>
                    ))}

                    <div>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setOptions([...options, ''])}
                        >
                            Add a value
                        </Button>
                    </div>

                    <p className="text-muted-foreground text-xs">
                        Removing a value here does not remove answers that
                        already use it.
                    </p>

                    <InputError message={errors.options} />
                    <InputError message={errors['options.0']} />
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor="default_value">Suggested value</Label>

                <Input
                    id="default_value"
                    name="default_value"
                    defaultValue={defaults?.default_value ?? ''}
                />

                <p className="text-muted-foreground text-xs">
                    Offered on the form when nothing has been filled in yet. It
                    is not stored until somebody saves it, so a field left alone
                    stays unanswered rather than quietly agreeing with the
                    suggestion.
                </p>

                <InputError message={errors.default_value} />
            </div>

            {chosen?.uses_pattern && (
                <div className="grid gap-2">
                    <Label htmlFor="pattern">Pattern</Label>

                    <Input
                        id="pattern"
                        name="pattern"
                        defaultValue={defaults?.pattern ?? ''}
                        placeholder="/^[A-Z]{2}-[0-9]+$/"
                    />

                    <p className="text-muted-foreground text-xs">
                        A regular expression, with delimiters. Answers are
                        checked against it on save.
                    </p>

                    <InputError message={errors.pattern} />
                </div>
            )}

            {chosen?.uses_length_limits && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="minimum_length">Shortest answer</Label>

                        <Input
                            id="minimum_length"
                            name="minimum_length"
                            type="number"
                            min={0}
                            max={4000}
                            defaultValue={defaults?.minimum_length ?? ''}
                        />

                        <InputError message={errors.minimum_length} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="maximum_length">Longest answer</Label>

                        <Input
                            id="maximum_length"
                            name="maximum_length"
                            type="number"
                            min={1}
                            max={4000}
                            defaultValue={defaults?.maximum_length ?? ''}
                        />

                        <InputError message={errors.maximum_length} />
                    </div>
                </div>
            )}
        </>
    );
}
