import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CustomFieldInput } from '@/types/custom-field';

type CustomFieldInputsProps = {
    fields: CustomFieldInput[];
    errors: Partial<Record<string, string>>;
    /** Frozen versions still show their answers; they just cannot take new ones. */
    readOnly?: boolean;
};

/**
 * The inputs for whatever fields a project has enabled here.
 *
 * One component for every type, driven by the definition, so enabling a field
 * in a project changes what the form asks for without a change here. Legacy
 * rendered these from a `switch` in `web_editor`-era PHP that emitted a
 * different input name per type — `custom_field_string_12`,
 * `custom_field_date_12_day` — which the receiving end then had to parse back
 * apart; these post as `custom_fields[12]` whatever the type.
 *
 * Uncontrolled, like the rest of the forms here: the server sends the answer
 * as the default and validation errors come back per field, so there is no
 * client-side copy of the value to keep in step.
 */
export default function CustomFieldInputs({
    fields,
    errors,
    readOnly = false,
}: CustomFieldInputsProps) {
    if (fields.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-4 rounded-lg border p-4">
            <p className="text-muted-foreground text-xs">
                Fields this project has added.
            </p>

            {fields.map((field) =>
                readOnly ? (
                    <div key={field.id} className="grid gap-1">
                        <span className="text-sm font-medium">
                            {field.label}
                        </span>

                        <span className="text-muted-foreground text-sm">
                            {answerText(field.answer)}
                        </span>
                    </div>
                ) : (
                    <CustomFieldControl
                        key={field.id}
                        field={field}
                        error={errors[`custom_fields.${field.id}`]}
                    />
                ),
            )}
        </div>
    );
}

function answerText(answer: string | string[]) {
    if (Array.isArray(answer)) {
        return answer.length === 0 ? 'Not filled in' : answer.join(', ');
    }

    return answer === '' ? 'Not filled in' : answer;
}

function CustomFieldControl({
    field,
    error,
}: {
    field: CustomFieldInput;
    error?: string;
}) {
    const name = `custom_fields[${field.id}]`;
    const id = `custom_field_${field.id}`;
    const answer = field.answer;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>
                {field.label}
                {field.is_required && (
                    <span className="text-destructive" aria-hidden>
                        *
                    </span>
                )}
            </Label>

            {renderControl(field, name, id, answer)}

            <InputError message={error} />
        </div>
    );
}

function renderControl(
    field: CustomFieldInput,
    name: string,
    id: string,
    answer: string | string[],
) {
    const single = Array.isArray(answer) ? '' : answer;
    const chosen = Array.isArray(answer) ? answer : [];

    switch (field.type) {
        case 'text':
            return (
                <textarea
                    id={id}
                    name={name}
                    defaultValue={single}
                    rows={3}
                    maxLength={field.maximum_length}
                    required={field.is_required}
                    className="border-input focus-visible:border-ring focus-visible:ring-ring/50 min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                />
            );

        case 'dropdown':
            return (
                <select
                    id={id}
                    name={name}
                    defaultValue={single}
                    required={field.is_required}
                    className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                >
                    {/* Present even when mandatory, so the form opens with nothing pre-chosen rather than with the first option silently accepted. */}
                    <option value="">—</option>

                    {field.options.map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </select>
            );

        case 'radio':
            return (
                <div className="grid gap-2">
                    {field.options.map((option) => (
                        <div key={option} className="flex items-center gap-2">
                            <input
                                type="radio"
                                id={`${id}_${option}`}
                                name={name}
                                value={option}
                                defaultChecked={single === option}
                                className="border-input size-4"
                            />

                            <Label
                                htmlFor={`${id}_${option}`}
                                className="font-normal"
                            >
                                {option}
                            </Label>
                        </div>
                    ))}
                </div>
            );

        case 'multi_select':
        case 'checkbox':
            return (
                <div className="grid gap-2">
                    {/*
                     * Says the field was on the form even when every box is
                     * left clear, which is how clearing one is told apart from
                     * not touching it. The server drops this entry before
                     * validating — see `normaliseCustomFieldInput`.
                     */}
                    <input type="hidden" name={`${name}[]`} value="" />

                    {field.options.map((option) => (
                        <div key={option} className="flex items-center gap-2">
                            <Checkbox
                                id={`${id}_${option}`}
                                name={`${name}[]`}
                                value={option}
                                defaultChecked={chosen.includes(option)}
                            />

                            <Label
                                htmlFor={`${id}_${option}`}
                                className="font-normal"
                            >
                                {option}
                            </Label>
                        </div>
                    ))}
                </div>
            );

        default:
            return (
                <Input
                    id={id}
                    name={name}
                    type={inputTypeFor(field.type)}
                    step={field.type === 'float' ? 'any' : undefined}
                    placeholder={
                        field.type === 'datetime'
                            ? 'YYYY-MM-DD HH:MM:SS'
                            : undefined
                    }
                    defaultValue={single}
                    maxLength={
                        field.type === 'string' || field.type === 'email'
                            ? field.maximum_length
                            : undefined
                    }
                    required={field.is_required}
                />
            );
    }
}

/**
 * The browser's own control where there is a fitting one.
 *
 * `datetime` stays a text box: `datetime-local` posts `Y-m-dTH:i`, which is
 * neither what the column holds nor what the server validates, and swapping the
 * separator in the browser would put the two formats one keystroke apart.
 */
function inputTypeFor(type: CustomFieldInput['type']) {
    switch (type) {
        case 'numeric':
        case 'float':
            return 'number';
        case 'email':
            return 'email';
        case 'date':
            return 'date';
        default:
            return 'text';
    }
}
