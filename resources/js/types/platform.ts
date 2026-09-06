export type PlatformProject = {
    id: number;
    name: string;
};

/**
 * A platform as the catalogue screen shows it. The plan-item count is what
 * says whether delete is available — the action refuses while any item sits
 * on the platform.
 */
export type PlatformSummary = {
    id: number;
    name: string;
    notes: string | null;
    enable_on_design: boolean;
    enable_on_execution: boolean;
    is_open: boolean;
    test_plans_count: number;
    test_case_versions_count: number;
    plan_items_count: number;
};

export type PlatformDetail = PlatformSummary;

/** A design-time platform the specification picker can offer. */
export type PlatformOption = {
    id: number;
    name: string;
    is_open: boolean;
};
