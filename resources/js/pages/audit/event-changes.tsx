import type { AuditChange } from '@/types/audit';

/**
 * Renders a record's `properties`.
 *
 * Two shapes arrive here, because the trail is written two ways. Changes
 * derived from a model come as `{ from, to }`, or as `{ changed: true }` with
 * no values where the attribute is one the model hides. Actions that describe
 * themselves by hand contribute plain scalars. Both are shown, rather than
 * assuming one, because assuming would silently drop half the trail.
 */
export function EventChanges({
    properties,
}: {
    properties: Record<string, unknown> | null;
}) {
    if (properties === null || Object.keys(properties).length === 0) {
        return null;
    }

    return (
        <dl className="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[minmax(0,10rem)_1fr]">
            {Object.entries(properties).map(([field, value]) => (
                <div key={field} className="contents">
                    <dt className="text-muted-foreground truncate">
                        {humanise(field)}
                    </dt>

                    <dd className="min-w-0 break-words">{describe(value)}</dd>
                </div>
            ))}
        </dl>
    );
}

function describe(value: unknown) {
    if (!isChange(value)) {
        return <span>{render(value)}</span>;
    }

    if (value.changed === true) {
        return (
            <span className="text-muted-foreground italic">
                changed, value not recorded
            </span>
        );
    }

    return (
        <span>
            <span className="text-muted-foreground line-through">
                {render(value.from)}
            </span>{' '}
            <span aria-hidden="true">→</span>{' '}
            <span className="sr-only">became</span>
            <span>{render(value.to)}</span>
        </span>
    );
}

/**
 * A `{ from, to }` or `{ changed }` object, as opposed to a scalar an action
 * wrote by hand. Tested on the keys rather than on the action, so a new action
 * needs no case here.
 */
function isChange(value: unknown): value is AuditChange {
    if (typeof value !== 'object' || value === null || Array.isArray(value)) {
        return false;
    }

    return ['from', 'to', 'changed'].some((key) => key in value);
}

function render(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'yes' : 'no';
    }

    if (Array.isArray(value)) {
        return value.length === 0 ? 'none' : value.join(', ');
    }

    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'bigint') {
        return value.toString();
    }

    /*
     * Whatever is left came out of a JSON column, so it is an object. The
     * fallback is for the types JSON cannot carry and `JSON.stringify` answers
     * `undefined` for, which nothing should ever put in `properties`.
     */
    return JSON.stringify(value) ?? '—';
}

function humanise(field: string): string {
    const words = field.replaceAll('_', ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}
