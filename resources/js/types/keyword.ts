export type KeywordProject = {
    id: number;
    name: string;
};

/**
 * A keyword as the catalogue screen shows it. The count is what says which
 * keywords are actually in play and how far a delete would reach.
 */
export type KeywordSummary = {
    id: number;
    name: string;
    notes: string | null;
    test_cases_count: number;
};

export type KeywordDetail = KeywordSummary;

/** A keyword as everything outside the catalogue needs it. */
export type KeywordOption = {
    id: number;
    name: string;
};

/** Which keywords the specification tree is filtered by, and how. */
export type KeywordFilter = {
    ids: number[];
    match: 'any' | 'all';
};
