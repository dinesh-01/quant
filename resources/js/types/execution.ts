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
    latest_status: string | null;
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

export const executionStatusLabels: Record<string, string> = {
    not_run: 'Not run',
    passed: 'Passed',
    failed: 'Failed',
    blocked: 'Blocked',
};
