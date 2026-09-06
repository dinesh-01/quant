import type { TreeSpec } from '@/types/requirements';

export function flattenSpecs(
    specs: TreeSpec[],
    depth = 0,
): { id: number; name: string; doc_id: string; depth: number }[] {
    return specs.flatMap((spec) => [
        { id: spec.id, name: spec.name, doc_id: spec.doc_id, depth },
        ...flattenSpecs(spec.children, depth + 1),
    ]);
}

export function subtreeSpecIds(specs: TreeSpec[], id: number): number[] {
    const match = findSpec(specs, id);

    if (match === null) {
        return [];
    }

    return [match.id, ...collectIds(match.children)];
}

function findSpec(specs: TreeSpec[], id: number): TreeSpec | null {
    for (const spec of specs) {
        if (spec.id === id) {
            return spec;
        }

        const child = findSpec(spec.children, id);

        if (child !== null) {
            return child;
        }
    }

    return null;
}

function collectIds(specs: TreeSpec[]): number[] {
    return specs.flatMap((spec) => [spec.id, ...collectIds(spec.children)]);
}
