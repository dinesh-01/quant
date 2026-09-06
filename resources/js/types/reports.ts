export type ReportProject = {
    id: number;
    name: string;
};

export type StatusCounts = {
    passed: number;
    failed: number;
    blocked: number;
    not_run: number;
};

export type PlanDashboardRow = {
    id: number;
    name: string;
    items: number;
    build: { id: number; name: string } | null;
    counts: StatusCounts | null;
};

export type PlanStatusReport = {
    build: { id: number; name: string } | null;
    total: number;
    counts: StatusCounts;
    items: {
        id: number;
        full_external_id: string;
        name: string;
        platform: string | null;
        status: string;
    }[];
};

export type TesterProgressRow = {
    user_id: number;
    name: string;
    assigned: number;
    passed: number;
    failed: number;
    blocked: number;
    not_run: number;
};

export type MilestoneBand = {
    items: number;
    passed: number;
    actual_percent: number;
    target_percent: number;
};

export type MilestoneProgressRow = {
    id: number;
    name: string;
    target_date: string;
    bands: {
        high: MilestoneBand;
        medium: MilestoneBand;
        low: MilestoneBand;
    };
};

export type CoveredRequirement = {
    id: number;
    doc_id: string;
    name: string;
    covering_items: number;
    passed_items: number;
    status: string;
};

export type UncoveredRequirement = {
    id: number;
    doc_id: string;
    name: string;
};

export type TimelineDay = {
    date: string;
    passed: number;
    failed: number;
    blocked: number;
    total: number;
};

export type ReportBaselineSummary = {
    id: number;
    name: string;
    build_id: number | null;
    build_name: string | null;
    created_at: string;
    total: number;
    counts: StatusCounts;
};

export type BaselineComparison = {
    baseline: {
        id: number;
        name: string;
        created_at: string;
    };
    live_counts: StatusCounts;
    baseline_counts: StatusCounts;
    changed: {
        full_external_id: string;
        name: string;
        platform: string | null;
        live: string | null;
        baseline: string | null;
    }[];
};
