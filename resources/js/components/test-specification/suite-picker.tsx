import type { ChangeEvent } from 'react';
import { flattenSuites } from '@/lib/specification-tree';
import type { SuiteBranch } from '@/types/test-specification';

const selectClasses =
    'border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none';

/**
 * Chooses a suite from the project as the target of a move or a copy.
 *
 * A flat `<select>` rather than a second tree: it is one tab stop, it is
 * typeable, and screen readers announce it as a list of choices. Nesting is
 * shown by indenting the option text, which is the only styling a native option
 * list carries across browsers.
 *
 * Cross-project copying is offered by `CopyTargetPicker`, which fetches other
 * projects and then renders this list for the chosen tree.
 */
export default function SuitePicker({
    suites,
    name,
    id,
    exclude = [],
    rootLabel,
    value,
    onValueChange,
    defaultValue,
}: {
    suites: SuiteBranch[];
    name: string;
    id: string;
    /** Suites that would be an invalid target, such as the moving suite's own subtree. */
    exclude?: number[];
    /** Offered as an empty value when the project root is a legal target. */
    rootLabel?: string;
    /**
     * Pass both to drive the picker from the caller, which is what a move form
     * needs so it can tell whether the target has actually changed.
     */
    value?: string;
    onValueChange?: (value: string) => void;
    defaultValue?: number | null;
}) {
    const options = flattenSuites(suites).filter(
        (suite) => !exclude.includes(suite.id),
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

            {options.map((suite) => (
                <option key={suite.id} value={suite.id}>
                    {'\u00a0'.repeat(suite.depth * 4)}
                    {suite.name}
                </option>
            ))}
        </select>
    );
}
