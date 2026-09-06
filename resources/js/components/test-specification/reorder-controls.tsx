import { Form } from '@inertiajs/react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { swap } from '@/lib/specification-tree';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * Move one item up or down among its siblings.
 *
 * The specification tree also reorders by dragging a grip. These buttons stay
 * on the detail pane so a keyboard (or a touch screen that cannot drag) can
 * still swap one place. Both post the same full sibling `order[]`.
 *
 * The order is submitted as hidden fields, so no client state is held: after
 * the redirect the tree prop is the new order and the buttons re-render from it.
 */
export default function ReorderControls({
    action,
    order,
    id,
    label,
    extra,
}: {
    action: RouteFormDefinition<'post'>;
    /** Every sibling id in their current order. */
    order: number[];
    /** The sibling being moved. */
    id: number;
    /** Names the thing being moved, for the buttons' accessible labels. */
    label: string;
    /** Any further fields the endpoint needs, such as the parent to reorder within. */
    extra?: Record<string, number | null>;
}) {
    const index = order.indexOf(id);

    if (index === -1 || order.length < 2) {
        return null;
    }

    return (
        <div className="flex items-center gap-2">
            <p className="text-muted-foreground text-sm">
                {index + 1} of {order.length}
            </p>

            <Move
                action={action}
                order={swap(order, index, -1)}
                extra={extra}
                disabled={index === 0}
                title={`Move ${label} up`}
            >
                <ChevronUp className="size-4" />
            </Move>

            <Move
                action={action}
                order={swap(order, index, 1)}
                extra={extra}
                disabled={index === order.length - 1}
                title={`Move ${label} down`}
            >
                <ChevronDown className="size-4" />
            </Move>
        </div>
    );
}

function Move({
    action,
    order,
    extra,
    disabled,
    title,
    children,
}: {
    action: RouteFormDefinition<'post'>;
    order: number[];
    extra?: Record<string, number | null>;
    disabled: boolean;
    title: string;
    children: ReactNode;
}) {
    return (
        <Form {...action} options={{ preserveScroll: true }}>
            {({ processing }) => (
                <>
                    {order.map((each) => (
                        <input
                            key={each}
                            type="hidden"
                            name="order[]"
                            value={each}
                        />
                    ))}

                    {Object.entries(extra ?? {}).map(([name, value]) => (
                        <input
                            key={name}
                            type="hidden"
                            name={name}
                            value={value ?? ''}
                        />
                    ))}

                    <Button
                        variant="outline"
                        size="icon"
                        className="size-7"
                        disabled={disabled || processing}
                        aria-label={title}
                        title={title}
                    >
                        {children}
                    </Button>
                </>
            )}
        </Form>
    );
}
