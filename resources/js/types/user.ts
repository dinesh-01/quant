export type GlobalRole = {
    id: number;
    name: string;
    description: string | null;
    /** Shown as a warning: this role passes every check in every project. */
    is_super_admin: boolean;
};

export type UserRow = {
    id: number;
    name: string;
    email: string;
    role_name: string | null;
    is_active: boolean;
    expires_at: string | null;
    /**
     * `is_active` and an unexpired date together. Kept separate so the list can
     * distinguish a deactivated account from one that merely ran out of time,
     * which are fixed in different ways.
     */
    is_usable: boolean;
};

export type UserFormValues = {
    id: number;
    name: string;
    email: string;
    role_id: number | null;
    is_active: boolean;
    expires_at: string | null;
    /** Drives the warning that you cannot switch off your own account. */
    is_self: boolean;
};
