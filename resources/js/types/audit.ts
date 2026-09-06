export type AuditActor = {
    name: string;
    email: string;
};

/**
 * What was acted on. `name` can be null when the subject has since been
 * deleted and the act that deleted it recorded no name to fall back on.
 */
export type AuditSubject = {
    type: string;
    id: number;
    name: string | null;
};

/**
 * A recorded change, as `field => { from, to }` — or `{ changed: true }` with
 * no values where the attribute is one the model hides, such as a password.
 * Actions that write their properties by hand contribute arbitrary scalars
 * alongside these, so a reader has to cope with both shapes.
 */
export type AuditChange = {
    from?: unknown;
    to?: unknown;
    changed?: boolean;
};

export type AuditEventRow = {
    id: number;
    action: string;
    label: string;
    /** Absent for acts performed by a console command, which has no session. */
    actor: AuditActor | null;
    subject: AuditSubject | null;
    properties: Record<string, unknown> | null;
    ip_address: string | null;
    recorded_at: string | null;
};

export type AuditActionOption = {
    value: string;
    label: string;
};
