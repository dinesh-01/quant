import type { AttachmentSummary } from '@/types/attachment';

/**
 * The project the current route is scoped to, shared on every response.
 *
 * Resolved from the route binding, not from the session, so two tabs looking at
 * two projects cannot interfere with each other.
 */
export type CurrentProject = {
    id: number;
    name: string;
    prefix: string;
    /** Sent so the sidebar can omit destinations that would only be refused. */
    can: {
        viewSpecification: boolean;
        viewRequirements: boolean;
        manageTestPlans: boolean;
        viewKeywords: boolean;
        viewPlatforms: boolean;
        assignCustomFields: boolean;
        manageMembers: boolean;
        selectPlans: boolean;
        viewReports: boolean;
        viewCodeTrackers: boolean;
        viewIssueTrackers: boolean;
    };
};

export type ProjectSummary = {
    id: number;
    name: string;
    prefix: string;
    description: string | null;
    is_active: boolean;
    is_public: boolean;
    test_suites_count: number;
    test_cases_count: number;
    test_plans_count: number;
    /**
     * Whether this user may open the project's specification.
     *
     * A project administrator sees every project but does not necessarily hold
     * a role inside each one, so the card links to the specification only when
     * this is true. Without it the list would offer links that answer 403.
     */
    can_open: boolean;
};

export type ProjectFormValues = {
    id: number;
    name: string;
    prefix: string;
    description: string | null;
    is_active: boolean;
    is_public: boolean;
    /** True once PREFIX-N ids have been issued, after which the prefix is fixed. */
    prefix_locked: boolean;
    test_cases_count: number;
    attachments: AttachmentSummary[];
};
