export type AssignableRole = {
    id: number;
    name: string;
    description: string | null;
};

export type ScopeMember = {
    id: number;
    name: string;
    email: string;
    role_id: number;
    role_name: string;
    /**
     * Whether this row is the signed-in user. Changing your own assignment is
     * refused server side unless you administer projects, because on a
     * restricted scope it is how you lock yourself out.
     */
    is_self: boolean;
};
