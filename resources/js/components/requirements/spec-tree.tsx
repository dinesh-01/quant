import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronRight, FileText, Folder } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import { show as requirementShow } from '@/routes/requirements/items';
import { show as specShow } from '@/routes/requirements/specs';
import type { RequirementsProject, TreeSpec } from '@/types/requirements';

type Selected = { type: 'spec' | 'requirement'; id: number } | null;

export default function SpecTree({
    project,
    specs,
    selected,
}: {
    project: RequirementsProject;
    specs: TreeSpec[];
    selected: Selected;
}) {
    if (specs.length === 0) {
        return (
            <p className="text-muted-foreground px-2 py-4 text-sm">
                No requirement specifications yet.
            </p>
        );
    }

    return (
        <ul className="space-y-0.5">
            {specs.map((spec) => (
                <SpecNode
                    key={spec.id}
                    project={project}
                    spec={spec}
                    selected={selected}
                    depth={0}
                />
            ))}
        </ul>
    );
}

function SpecNode({
    project,
    spec,
    selected,
    depth,
}: {
    project: RequirementsProject;
    spec: TreeSpec;
    selected: Selected;
    depth: number;
}) {
    const containsSelection = subtreeHolds(spec, selected);
    const [expanded, setExpanded] = useState(containsSelection || depth === 0);
    const hasChildren =
        spec.children.length > 0 || spec.requirements.length > 0;
    const isSelected = selected?.type === 'spec' && selected.id === spec.id;

    return (
        <li>
            <div
                className="flex items-center gap-1"
                style={{ paddingLeft: `${depth * 12}px` }}
            >
                <button
                    type="button"
                    onClick={() => setExpanded(!expanded)}
                    className={cn(
                        'text-muted-foreground hover:text-foreground shrink-0 rounded p-0.5',
                        !hasChildren && 'invisible',
                    )}
                    aria-label={expanded ? 'Collapse' : 'Expand'}
                >
                    {expanded ? (
                        <ChevronDown className="size-3.5" />
                    ) : (
                        <ChevronRight className="size-3.5" />
                    )}
                </button>

                <Link
                    href={specShow([project.id, spec.id])}
                    className={cn(
                        'flex min-w-0 flex-1 items-center gap-1.5 rounded px-1.5 py-1 text-sm',
                        isSelected
                            ? 'bg-accent text-accent-foreground'
                            : 'hover:bg-muted',
                    )}
                >
                    <Folder className="size-3.5 shrink-0" />
                    <span className="truncate">
                        {spec.doc_id} {spec.name}
                    </span>
                </Link>
            </div>

            {expanded && hasChildren && (
                <ul className="space-y-0.5">
                    {spec.children.map((child) => (
                        <SpecNode
                            key={child.id}
                            project={project}
                            spec={child}
                            selected={selected}
                            depth={depth + 1}
                        />
                    ))}
                    {spec.requirements.map((requirement) => {
                        const isReq =
                            selected?.type === 'requirement' &&
                            selected.id === requirement.id;

                        return (
                            <li
                                key={requirement.id}
                                className="flex items-center gap-1"
                                style={{
                                    paddingLeft: `${(depth + 1) * 12 + 22}px`,
                                }}
                            >
                                <Link
                                    href={requirementShow([
                                        project.id,
                                        requirement.id,
                                    ])}
                                    className={cn(
                                        'flex min-w-0 flex-1 items-center gap-1.5 rounded px-1.5 py-1 text-sm',
                                        isReq
                                            ? 'bg-accent text-accent-foreground'
                                            : 'hover:bg-muted',
                                    )}
                                >
                                    <FileText className="size-3.5 shrink-0" />
                                    <span className="truncate">
                                        {requirement.doc_id} {requirement.name}
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </li>
    );
}

function subtreeHolds(spec: TreeSpec, selected: Selected): boolean {
    if (selected === null) {
        return false;
    }

    if (selected.type === 'spec' && selected.id === spec.id) {
        return true;
    }

    if (
        selected.type === 'requirement' &&
        spec.requirements.some((requirement) => requirement.id === selected.id)
    ) {
        return true;
    }

    return spec.children.some((child) => subtreeHolds(child, selected));
}
