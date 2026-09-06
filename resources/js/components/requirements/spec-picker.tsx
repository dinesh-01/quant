import type { ChangeEvent } from 'react';
import { flattenSpecs } from '@/lib/requirement-tree';
import type { TreeSpec } from '@/types/requirements';

const selectClasses =
    'border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none';

export default function SpecPicker({
    specs,
    name,
    id,
    exclude = [],
    rootLabel,
    value,
    onValueChange,
    defaultValue,
}: {
    specs: TreeSpec[];
    name: string;
    id: string;
    exclude?: number[];
    rootLabel?: string;
    value?: string;
    onValueChange?: (value: string) => void;
    defaultValue?: number | null;
}) {
    const options = flattenSpecs(specs).filter(
        (spec) => !exclude.includes(spec.id),
    );

    const controlled =
        value !== undefined
            ? {
                  value,
                  onChange: (event: ChangeEvent<HTMLSelectElement>) =>
                      onValueChange?.(event.target.value),
              }
            : { defaultValue: defaultValue ?? '' };

    return (
        <select id={id} name={name} className={selectClasses} {...controlled}>
            {rootLabel !== undefined && <option value="">{rootLabel}</option>}
            {options.map((spec) => (
                <option key={spec.id} value={spec.id}>
                    {'— '.repeat(spec.depth)}
                    {spec.doc_id} {spec.name}
                </option>
            ))}
        </select>
    );
}
