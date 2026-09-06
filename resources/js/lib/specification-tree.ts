import type { SuiteBranch, TreeSuite } from '@/types/test-specification';

/**
 * Queries over the specification tree.
 *
 * The whole tree already arrives as one page prop, so the move targets and the
 * sibling order that reordering needs are derivable on the client. Asking the
 * server for them would add props that can only ever agree with the tree, and a
 * round trip before the controls could render.
 *
 * Every function walks in the order the server sent, which is `sort_order` then
 * `id` — the same order reordering renumbers.
 */

export type FlatSuite = {
    id: number;
    name: string;
    depth: number;
};

/**
 * Every suite in the project, depth first, each carrying how deeply it nests so
 * a flat `<select>` can still show the shape of the tree.
 */
export function flattenSuites(suites: SuiteBranch[], depth = 0): FlatSuite[] {
    return suites.flatMap((suite) => [
        { id: suite.id, name: suite.name, depth },
        ...flattenSuites(suite.children, depth + 1),
    ]);
}

/**
 * A suite and every suite beneath it.
 *
 * These are exactly the targets a suite may not move or copy into. The server
 * refuses such a request, but offering it and then rejecting it would be a
 * worse way to say so than not offering it.
 */
export function subtreeIds(suites: TreeSuite[], suiteId: number): number[] {
    for (const suite of suites) {
        if (suite.id === suiteId) {
            return [
                suite.id,
                ...flattenSuites(suite.children).map((each) => each.id),
            ];
        }

        const found = subtreeIds(suite.children, suiteId);

        if (found.length > 0) {
            return found;
        }
    }

    return [];
}

/**
 * The ids of a suite's siblings in their current order, with the parent they
 * sit under. A top level suite's parent is null, which is what the reorder
 * endpoint expects for the project root.
 */
export function suiteSiblings(
    suites: TreeSuite[],
    suiteId: number,
): { parentId: number | null; order: number[] } {
    const search = (
        siblings: TreeSuite[],
        parentId: number | null,
    ): { parentId: number | null; order: number[] } | null => {
        if (siblings.some((suite) => suite.id === suiteId)) {
            return { parentId, order: siblings.map((suite) => suite.id) };
        }

        for (const suite of siblings) {
            const found = search(suite.children, suite.id);

            if (found !== null) {
                return found;
            }
        }

        return null;
    };

    return search(suites, null) ?? { parentId: null, order: [] };
}

/**
 * The ids of every test case in the suite holding the given case, in their
 * current order.
 */
export function caseSiblings(suites: TreeSuite[], caseId: number): number[] {
    for (const suite of suites) {
        if (suite.cases.some((testCase) => testCase.id === caseId)) {
            return suite.cases.map((testCase) => testCase.id);
        }

        const found = caseSiblings(suite.children, caseId);

        if (found.length > 0) {
            return found;
        }
    }

    return [];
}

/**
 * The same list with the item at `index` swapped with its neighbour, or the
 * list untouched when there is no neighbour that way.
 */
export function swap(
    order: number[],
    index: number,
    direction: -1 | 1,
): number[] {
    const target = index + direction;

    if (index < 0 || target < 0 || target >= order.length) {
        return order;
    }

    const moved = [...order];
    [moved[index], moved[target]] = [moved[target], moved[index]];

    return moved;
}

/**
 * Every suite id in the tree, depth first.
 */
export function suiteIds(suites: TreeSuite[]): number[] {
    return suites.flatMap((suite) => [suite.id, ...suiteIds(suite.children)]);
}

export type TreeSelection = { type: 'suite' | 'case'; id: number } | null;

/**
 * Ancestor suite ids that must stay open for the selected row to remain
 * visible. The selected suite itself is not included: Collapse all should
 * close it. A selected case still needs its parent suite.
 *
 * An empty array means "found this suite" as well as "nothing selected", so
 * the walk uses null for "not in this branch" and cannot recurse with
 * `nested.length > 0` the way a path-including-self helper can.
 */
export function suiteIdsOnPath(
    suites: TreeSuite[],
    selected: TreeSelection,
): number[] {
    if (selected === null) {
        return [];
    }

    const walk = (nodes: TreeSuite[]): number[] | null => {
        for (const suite of nodes) {
            if (selected.type === 'suite' && suite.id === selected.id) {
                return [];
            }

            if (
                selected.type === 'case' &&
                suite.cases.some((testCase) => testCase.id === selected.id)
            ) {
                return [suite.id];
            }

            const nested = walk(suite.children);

            if (nested !== null) {
                return [suite.id, ...nested];
            }
        }

        return null;
    };

    return walk(suites) ?? [];
}

/**
 * Ancestors plus the selected suite, so landing on a suite shows its children.
 */
export function suitesRevealingSelection(
    suites: TreeSuite[],
    selected: TreeSelection,
): number[] {
    const path = suiteIdsOnPath(suites, selected);

    if (selected?.type === 'suite') {
        return [...path, selected.id];
    }

    return path;
}

/**
 * Roots start open, and so does the branch that holds the selected node.
 */
export function initiallyExpandedSuiteIds(
    suites: TreeSuite[],
    selected: TreeSelection,
): number[] {
    return [
        ...new Set([
            ...suites.map((suite) => suite.id),
            ...suitesRevealingSelection(suites, selected),
        ]),
    ];
}
