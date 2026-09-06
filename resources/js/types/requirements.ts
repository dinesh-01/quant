export type RequirementsProject = {
    id: number;
    name: string;
    prefix: string;
};

export type RequirementsAbilities = {
    manage: boolean;
    unfreeze: boolean;
    coverage: boolean;
    monitor: boolean;
};

export type TreeRequirement = {
    id: number;
    name: string;
    doc_id: string;
};

export type TreeSpec = {
    id: number;
    name: string;
    doc_id: string;
    children: TreeSpec[];
    requirements: TreeRequirement[];
};

export type SpecDetail = {
    id: number;
    name: string;
    doc_id: string;
    description: string | null;
    parent_id: number | null;
    path: { id: number; name: string; doc_id: string }[];
};

export type RequirementCoverageLink = {
    id: number;
    test_case_id: number;
    test_case_version_id: number;
    test_case_version: number;
    full_external_id: string;
    name: string;
};

export type CoverableCase = {
    id: number;
    test_case_id: number;
    version: number;
    full_external_id: string;
    name: string;
};

export type RequirementVersionSummary = {
    id: number;
    version: number;
    is_open: boolean;
};

export type RequirementVersionDetail = RequirementVersionSummary & {
    scope: string | null;
    status: string;
    type: string;
    expected_coverage: number;
    author: string | null;
    updater: string | null;
    coverages: RequirementCoverageLink[];
    coverable: CoverableCase[];
};

export type RequirementDetail = {
    id: number;
    name: string;
    doc_id: string;
    requirement_spec_id: number;
    spec_name: string;
    versions: RequirementVersionSummary[];
    version: RequirementVersionDetail | null;
    watching_id: number | null;
};

export type RequirementsSelection =
    | { type: 'spec'; spec: SpecDetail }
    | { type: 'requirement'; requirement: RequirementDetail }
    | null;

export type EnumOption = {
    value: string;
    label: string;
};

export const requirementStatusLabels: Record<string, string> = {
    draft: 'Draft',
    review: 'Review',
    rework: 'Rework',
    finish: 'Finish',
    implemented: 'Implemented',
    valid: 'Valid',
    not_testable: 'Not testable',
    obsolete: 'Obsolete',
};

export const requirementTypeLabels: Record<string, string> = {
    informational: 'Informational',
    feature: 'Feature',
    use_case: 'Use case',
    interface: 'Interface',
    non_functional: 'Non-functional',
    constraint: 'Constraint',
    system_function: 'System function',
};
