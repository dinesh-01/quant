export type ExecutionBuild = {
    id: number;
    name: string;
    is_open: boolean;
    is_active?: boolean;
};

export type ExecutionListItem = {
    id: number;
    full_external_id: string;
    name: string;
    version: number;
    platform: string | null;
    suite: string;
    priority: string;
    keywords: string[];
    assigned_to_viewer: boolean;
    latest_status: string | null;
};

export type ExecutionPlan = {
    id: number;
    name: string;
    external_id: string;
    is_open: boolean;
};

export type RunCounts = {
    passed: number;
    failed: number;
    blocked: number;
    not_run: number;
};

export type ExecutionStepResult = {
    id: number;
    test_case_step_id: number;
    status: string;
    notes: string | null;
};

export type ExecutionIssue = {
    id: number;
    issue_id: string;
    issue_url: string | null;
    issue_status: string | null;
    issue_summary: string | null;
};

export type ExecutionRecord = {
    id: number;
    status: string;
    notes: string | null;
    duration: string | null;
    is_draft: boolean;
    version: number;
    tester: string | null;
    executed_at: string | null;
    steps: ExecutionStepResult[];
    issues: ExecutionIssue[];
};

export type ExecutionCaseStep = {
    id: number;
    sort_order: number;
    actions: string | null;
    expected_results: string | null;
};

export type ExecutionShowItem = {
    id: number;
    test_case_id: number;
    full_external_id: string;
    name: string;
    version: number;
    summary: string | null;
    preconditions: string | null;
    platform: string | null;
    priority: string;
    keywords: string[];
    assigned_to_viewer: boolean;
    steps: ExecutionCaseStep[];
};

export const executionStatusLabels: Record<string, string> = {
    not_run: 'Not run',
    passed: 'Passed',
    failed: 'Failed',
    blocked: 'Blocked',
};
