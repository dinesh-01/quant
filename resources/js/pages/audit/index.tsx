import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { Search, Terminal, Trash2 } from 'lucide-react';
import EventLogController from '@/actions/App/Http/Controllers/Audit/EventLogController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/events';
import type { AuditActionOption, AuditEventRow } from '@/types/audit';
import type { Paginated } from '@/types/pagination';
import { EventChanges } from './event-changes';

type EventLogProps = {
    events: Paginated<AuditEventRow>;
    filters: { action: string; actor: string };
    actions: AuditActionOption[];
    can: { prune: boolean };
};

/**
 * The shortest retention the server will accept, mirrored here so the field
 * cannot be set below it before the request is made. The server rule is what
 * enforces it.
 */
const MINIMUM_KEEP_DAYS = 30;

const selectClasses =
    'border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]';

export default function EventLog({
    events,
    filters,
    actions,
    can,
}: EventLogProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Event log', href: index() }],
    });

    return (
        <>
            <Head title="Event log" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Event log"
                    description="Who changed what, and when. Records cannot be edited."
                />

                <Form
                    {...EventLogController.index.form()}
                    className="flex flex-wrap items-end gap-2"
                >
                    <div className="space-y-1">
                        <Label htmlFor="actor">Person</Label>

                        <Input
                            id="actor"
                            name="actor"
                            defaultValue={filters.actor}
                            placeholder="Name or email"
                            className="w-56"
                        />
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="action">Act</Label>

                        <select
                            id="action"
                            name="action"
                            defaultValue={filters.action}
                            className={selectClasses}
                        >
                            <option value="">Everything</option>

                            {actions.map((action) => (
                                <option key={action.value} value={action.value}>
                                    {action.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <Button type="submit" variant="secondary">
                        <Search className="size-4" />
                        Filter
                    </Button>
                </Form>

                {events.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-12 text-center">
                        <p className="text-muted-foreground text-sm">
                            {filters.action === '' && filters.actor === ''
                                ? 'Nothing has been recorded yet.'
                                : 'No record matches that filter.'}
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {events.data.map((event) => (
                            <li key={event.id} className="space-y-2 p-4">
                                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                    <div className="flex flex-wrap items-baseline gap-2">
                                        <span className="font-medium">
                                            {event.label}
                                        </span>

                                        {event.subject !== null && (
                                            <span className="text-muted-foreground text-sm">
                                                {event.subject.type}
                                                {event.subject.name !== null
                                                    ? ` · ${event.subject.name}`
                                                    : ` #${event.subject.id}`}
                                            </span>
                                        )}
                                    </div>

                                    <span className="text-muted-foreground text-xs">
                                        {formatRecordedAt(event.recorded_at)}
                                    </span>
                                </div>

                                <div className="flex flex-wrap items-center gap-2 text-sm">
                                    {event.actor === null ? (
                                        <Badge variant="secondary">
                                            <Terminal className="size-3" />
                                            Console
                                        </Badge>
                                    ) : (
                                        <span title={event.actor.email}>
                                            {event.actor.name}
                                        </span>
                                    )}

                                    {event.ip_address !== null && (
                                        <span className="text-muted-foreground text-xs">
                                            from {event.ip_address}
                                        </span>
                                    )}
                                </div>

                                <EventChanges properties={event.properties} />
                            </li>
                        ))}
                    </ul>
                )}

                {events.last_page > 1 && (
                    <div className="flex items-center justify-between gap-4">
                        <p className="text-muted-foreground text-sm">
                            {events.from}–{events.to} of {events.total}
                        </p>

                        <div className="flex gap-2">
                            <Button
                                asChild={events.prev_page_url !== null}
                                variant="secondary"
                                size="sm"
                                disabled={events.prev_page_url === null}
                            >
                                {events.prev_page_url === null ? (
                                    <span>Newer</span>
                                ) : (
                                    <a href={events.prev_page_url}>Newer</a>
                                )}
                            </Button>

                            <Button
                                asChild={events.next_page_url !== null}
                                variant="secondary"
                                size="sm"
                                disabled={events.next_page_url === null}
                            >
                                {events.next_page_url === null ? (
                                    <span>Older</span>
                                ) : (
                                    <a href={events.next_page_url}>Older</a>
                                )}
                            </Button>
                        </div>
                    </div>
                )}

                {can.prune && (
                    <div className="space-y-3 rounded-lg border border-dashed p-4">
                        <div>
                            <h2 className="font-medium">Discard old records</h2>

                            <p className="text-muted-foreground text-sm">
                                Sets a retention period. Recent records cannot
                                be discarded, and the discarding is itself
                                recorded.
                            </p>
                        </div>

                        <Form
                            {...EventLogController.destroy.form()}
                            options={{ preserveScroll: true }}
                            className="flex flex-wrap items-end gap-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="space-y-1">
                                        <Label htmlFor="keep_days">
                                            Keep the last
                                        </Label>

                                        <div className="flex items-center gap-2">
                                            <Input
                                                id="keep_days"
                                                name="keep_days"
                                                type="number"
                                                min={MINIMUM_KEEP_DAYS}
                                                defaultValue={365}
                                                className="w-28"
                                            />

                                            <span className="text-muted-foreground text-sm">
                                                days
                                            </span>
                                        </div>

                                        <InputError
                                            message={errors.keep_days}
                                        />
                                    </div>

                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        <Trash2 className="size-4" />
                                        Discard
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                )}
            </div>
        </>
    );
}

/**
 * Rendered in the reader's locale, not the actor's — the row stores an instant
 * rather than a formatted string precisely so this can be done here.
 */
function formatRecordedAt(recordedAt: string | null): string {
    if (recordedAt === null) {
        return 'Unknown time';
    }

    return new Date(recordedAt).toLocaleString();
}
