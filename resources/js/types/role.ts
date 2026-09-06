export type AbilityOption = {
    value: string;
    label: string;
    /**
     * System abilities are read from a user's global role only, so granting
     * one on a project or plan role has no effect. The form says so rather
     * than hiding them, since the same role may be used globally.
     */
    is_system: boolean;
};

export type AbilityGroup = {
    name: string;
    abilities: AbilityOption[];
};

export type RoleSummary = {
    id: number;
    name: string;
    description: string | null;
    is_super_admin: boolean;
    is_default: boolean;
    abilities_count: number;
    /** Users for whom this is their global role. */
    users_count: number;
    /** Project and plan assignments combined. */
    assignments_count: number;
};

export type RoleFormValues = {
    id: number;
    name: string;
    description: string | null;
    abilities: string[];
    is_super_admin: boolean;
    is_default: boolean;
    users_count: number;
    assignments_count: number;
    /** Drives the warning that you are editing what you can do yourself. */
    is_own_role: boolean;
};
