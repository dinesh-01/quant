import type { AttachmentSummary } from '@/types/attachment';
import type { CustomFieldInput } from '@/types/custom-field';
import type { KeywordOption } from '@/types/keyword';

export type SpecificationProject = {
    id: number;
    name: string;
    prefix: string;
};

/**
 * What the acting user may do in this project. Resolved once on the server,
 * because abilities are scoped to the project rather than to a node.
 */
export type SpecificationAbilities = {
    manage: boolean;
    freeze: boolean;
    deleteFrozen: boolean;
    assignKeywords: boolean;
    viewKeywords: boolean;
    viewPlatforms: boolean;
    coverage: boolean;
};

export type TreeCase = {
    id: number;
    name: string;
    full_external_id: string;
};

export type SuiteBranch = {
    id: number;
    name: string;
    children: SuiteBranch[];
};

export type TreeSuite = Omit<SuiteBranch, 'children'> & {
    children: TreeSuite[];
    cases: TreeCase[];
};

export type SuiteDetail = {
    id: number;
    name: string;
    description: string | null;
    parent_id: number | null;
    path: { id: number; name: string }[];
    attachments: AttachmentSummary[];
    custom_fields: CustomFieldInput[];
};

export type StepDetail = {
    id: number;
    sort_order: number;
    actions: string | null;
    expected_results: string | null;
    execution_type: string;
};

export type VersionSummary = {
    id: number;
    version: number;
    is_open: boolean;
};

export type VersionDetail = VersionSummary & {
    status: string;
    summary: string | null;
    preconditions: string | null;
    importance: string;
    execution_type: string;
    estimated_duration: string | null;
    author: string | null;
    updater: string | null;
    steps: StepDetail[];
    /** On the version, so switching version switches the evidence with it. */
    attachments: AttachmentSummary[];
    custom_fields: CustomFieldInput[];
    /**
     * On the version, the opposite of keywords: a claim about this revision
     * of the steps, so switching version switches the tags with it.
     */
    platforms: { id: number; name: string }[];
    coverages: CaseCoverageLink[];
    coverable: CoverableRequirement[];
    script_links: ScriptLink[];
};

export type ScriptLink = {
    id: number;
    project_key: string;
    repository: string;
    path: string;
    branch: string | null;
    commit: string | null;
    url: string | null;
};

export type CaseCoverageLink = {
    id: number;
    requirement_id: number;
    requirement_version: number;
    doc_id: string;
    name: string;
};

export type CoverableRequirement = {
    id: number;
    requirement_id: number;
    version: number;
    doc_id: string;
    name: string;
};

export type CaseDetail = {
    id: number;
    name: string;
    /** On the case rather than the version, so switching version keeps them. */
    keywords: KeywordOption[];
    external_id: number;
    full_external_id: string;
    test_suite_id: number;
    suite_name: string;
    versions: VersionSummary[];
    version: VersionDetail | null;
    relations: CaseRelation[];
    relatable: RelatableCase[];
};

export type CaseRelation = {
    id: number;
    type: string;
    label: string;
    other_id: number;
    other_name: string;
    full_external_id: string;
    outgoing: boolean;
};

export type RelatableCase = {
    id: number;
    name: string;
    full_external_id: string;
};

export type Selection =
    | { type: 'suite'; suite: SuiteDetail }
    | { type: 'case'; case: CaseDetail }
    | null;

export type SearchResult = {
    id: number;
    name: string;
    full_external_id: string;
    suite_name: string;
};

export const statusLabels: Record<string, string> = {
    draft: 'Draft',
    ready_for_review: 'Ready for review',
    review_in_progress: 'Review in progress',
    rework: 'Rework',
    obsolete: 'Obsolete',
    future: 'Future',
    final: 'Final',
};

export const importanceLabels: Record<string, string> = {
    low: 'Low',
    medium: 'Medium',
    high: 'High',
};

export const executionTypeLabels: Record<string, string> = {
    manual: 'Manual',
    automated: 'Automated',
};
