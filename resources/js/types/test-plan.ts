export type PlanSummary = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    /** Whether the plan still accepts execution results. */
    is_open: boolean;
    is_public: boolean;
};

export type PlanProject = {
    id: number;
    name: string;
};

export type PlanContentsProject = PlanProject & {
    prefix: string;
};

export type PlanContents = {
    id: number;
    name: string;
    is_open: boolean;
    has_null_platform_items: boolean;
};

export type PlanPlatformOption = {
    id: number;
    name: string;
    enable_on_execution: boolean;
    is_open: boolean;
    assigned: boolean;
};

export type PlanBuildSummary = {
    id: number;
    name: string;
    notes: string | null;
    is_active: boolean;
    is_open: boolean;
    release_date: string | null;
};

export type PlanItemSummary = {
    id: number;
    sort_order: number;
    urgency: string;
    platform: { id: number; name: string } | null;
    version_id: number;
    version: number;
    latest_version: number;
    test_case_id: number;
    test_case_name: string;
    full_external_id: string;
};

export type LinkableCase = {
    version_id: number;
    name: string;
    full_external_id: string;
    version: number;
};

export type PlanMilestone = {
    id: number;
    name: string;
    target_date: string;
    start_date: string | null;
    high_percent: number;
    medium_percent: number;
    low_percent: number;
};

export type PlanContentsAbilities = {
    editPlan: boolean;
    managePlanPlatforms: boolean;
    manageBuilds: boolean;
    planTestCases: boolean;
    setUrgency: boolean;
    updateLinkedVersions: boolean;
    viewPlatforms: boolean;
    assignTesters: boolean;
    manageMilestones: boolean;
    manageAttachments: boolean;
    viewAttachments: boolean;
};

export type AssignableTester = {
    id: number;
    name: string;
};

export type TesterAssignmentSummary = {
    id: number;
    test_plan_item_id: number;
    build_id: number;
    user_id: number;
    user_name: string;
    status: string;
    deadline_at: string | null;
};

export type AssignmentStatusOption = {
    value: string;
    label: string;
};

export type SelectablePlan = {
    id: number;
    name: string;
    description: string | null;
    is_open: boolean;
    is_public: boolean;
    can_execute: boolean;
    can_assign: boolean;
};
