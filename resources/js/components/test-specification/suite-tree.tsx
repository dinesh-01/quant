import {
    closestCenter,
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    type CollisionDetection,
    type DragEndEvent,
    type DragStartEvent,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Link, router } from '@inertiajs/react';
import { FileText, GripVertical } from 'lucide-react';
import { useMemo, useState } from 'react';
import TestCaseController from '@/actions/App/Http/Controllers/TestSpecification/TestCaseController';
import TestSuiteController from '@/actions/App/Http/Controllers/TestSpecification/TestSuiteController';
import { MockIcon } from '@/components/chrome/mock-icon';
import {
    initiallyExpandedSuiteIds,
    suiteIdHoldingCase,
    suitesRevealingSelection,
} from '@/lib/specification-tree';
import { cn } from '@/lib/utils';
import { show as caseShow } from '@/routes/specification/cases';
import { show as suiteShow } from '@/routes/specification/suites';
import type {
    SpecificationProject,
    TreeCase,
    TreeSuite,
} from '@/types/test-specification';

type Selected = { type: 'suite' | 'case'; id: number } | null;

type DragNode = {
    kind: 'suite' | 'case';
    id: number;
    name: string;
    parentId: number | null;
    container: string;
    order: number[];
};

function suiteItemId(id: number): string {
    return `suite:${id}`;
}

function caseItemId(id: number): string {
    return `case:${id}`;
}

function suiteContainer(parentId: number | null): string {
    return parentId === null ? 'suites:root' : `suites:${parentId}`;
}

function countCases(suite: TreeSuite): number {
    return (
        suite.cases.length +
        suite.children.reduce((sum, child) => sum + countCases(child), 0)
    );
}

function caseContainer(suiteId: number): string {
    return `cases:${suiteId}`;
}

const sameContainerCollision: CollisionDetection = (args) => {
    const container = args.active.data.current?.container;

    return closestCenter({
        ...args,
        droppableContainers: args.droppableContainers.filter(
            (item) => item.data.current?.container === container,
        ),
    });
};

/**
 * The specification tree.
 *
 * Selecting a node is an ordinary Inertia visit, so every suite and case has a
 * shareable URL and the browser's back button works. The whole tree arrives in
 * one prop, so expanding a node never fetches anything.
 *
 * Expansion is local state. A visit to another node on this screen must
 * preserve it (`preserveState` on the links) or Collapse all snaps back open
 * when the page remounts. Opening a newly selected suite is done during render
 * when the selected key changes, not in an effect — an effect that re-adds the
 * selected suite also undoes a user collapse on every prop identity change.
 *
 * Drag and drop reorders siblings only and posts the same full `order[]` the
 * up/down buttons use. Re-parenting stays on the move pickers: a drop into
 * another suite is a different action and is refused here.
 */
export default function SuiteTree({
    project,
    suites,
    selected,
    canManage,
}: {
    project: SpecificationProject;
    suites: TreeSuite[];
    selected: Selected;
    canManage: boolean;
}) {
    const selectedKey =
        selected === null ? '' : `${selected.type}:${selected.id}`;

    const [expandedIds, setExpandedIds] = useState<number[]>(() =>
        initiallyExpandedSuiteIds(suites, selected),
    );
    const [openedFor, setOpenedFor] = useState(selectedKey);
    const [active, setActive] = useState<DragNode | null>(null);

    if (openedFor !== selectedKey) {
        setOpenedFor(selectedKey);
        setExpandedIds((current) => [
            ...new Set([
                ...current,
                ...suitesRevealingSelection(suites, selected),
            ]),
        ]);
    }

    const expanded = useMemo(() => new Set(expandedIds), [expandedIds]);
    const rootSuiteIds = useMemo(
        () => suites.map((suite) => suite.id),
        [suites],
    );
    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const onToggle = (id: number): void => {
        setExpandedIds((current) =>
            current.includes(id)
                ? current.filter((each) => each !== id)
                : [...current, id],
        );
    };

    const onDragStart = (event: DragStartEvent): void => {
        setActive((event.active.data.current as DragNode | undefined) ?? null);
    };

    const onDragEnd = (event: DragEndEvent): void => {
        setActive(null);

        if (!canManage) {
            return;
        }

        const { active: dragged, over } = event;

        if (!over || dragged.id === over.id) {
            return;
        }

        const from = dragged.data.current as DragNode | undefined;
        const to = over.data.current as DragNode | undefined;

        if (
            from === undefined ||
            to === undefined ||
            from.container !== to.container
        ) {
            return;
        }

        const fromIndex = from.order.indexOf(from.id);
        const toIndex = from.order.indexOf(to.id);

        if (fromIndex < 0 || toIndex < 0) {
            return;
        }

        const next = arrayMove(from.order, fromIndex, toIndex);

        if (next.every((id, index) => id === from.order[index])) {
            return;
        }

        if (from.kind === 'suite') {
            router.post(
                TestSuiteController.reorder.url(project.id),
                { parent_id: from.parentId, order: next },
                { preserveScroll: true },
            );

            return;
        }

        if (from.parentId === null) {
            return;
        }

        router.post(
            TestCaseController.reorder.url(from.parentId),
            { order: next },
            { preserveScroll: true },
        );
    };

    if (suites.length === 0) {
        return (
            <p className="text-muted-foreground px-2 py-4 text-sm">
                No test suites yet.
            </p>
        );
    }

    const totalCases = useMemo(
        () => suites.reduce((sum, suite) => sum + countCases(suite), 0),
        [suites],
    );

    const highlightedSuiteId =
        selected === null
            ? null
            : selected.type === 'suite'
              ? selected.id
              : suiteIdHoldingCase(suites, selected.id);

    const tree = (
        <ul>
            <li>
                <div className="flex items-center gap-2 rounded-lg px-2.5 py-[7px] text-[13.5px]">
                    <MockIcon
                        name="chev-down"
                        className="text-text-subtle size-3.5 shrink-0"
                    />
                    <MockIcon name="folder" className="size-4 shrink-0" />
                    <span className="truncate">{project.name}</span>
                    <span className="text-text-subtle ml-auto text-[11.5px]">
                        {totalCases}
                    </span>
                </div>
            </li>
            <SortableContext
                items={rootSuiteIds.map(suiteItemId)}
                strategy={verticalListSortingStrategy}
            >
                {suites.map((suite) => (
                    <SuiteNode
                        key={suite.id}
                        project={project}
                        suite={suite}
                        parentId={null}
                        siblingIds={rootSuiteIds}
                        selected={selected}
                        highlightedSuiteId={highlightedSuiteId}
                        depth={1}
                        expanded={expanded}
                        canManage={canManage}
                        onToggle={onToggle}
                    />
                ))}
            </SortableContext>
        </ul>
    );

    return (
        <div>
            <DndContext
                sensors={sensors}
                collisionDetection={sameContainerCollision}
                onDragStart={onDragStart}
                onDragEnd={onDragEnd}
                onDragCancel={() => setActive(null)}
            >
                {tree}
                <DragOverlay>
                    {active ? (
                        <div className="bg-background flex items-center gap-2 rounded border px-2 py-1 text-sm shadow-sm">
                            {active.kind === 'suite' ? (
                                <MockIcon
                                    name="folder"
                                    className="text-muted-foreground size-4"
                                />
                            ) : (
                                <FileText className="text-muted-foreground size-4" />
                            )}
                            <span className="truncate">{active.name}</span>
                        </div>
                    ) : null}
                </DragOverlay>
            </DndContext>
        </div>
    );
}

function SuiteNode({
    project,
    suite,
    parentId,
    siblingIds,
    selected,
    highlightedSuiteId,
    depth,
    expanded,
    canManage,
    onToggle,
}: {
    project: SpecificationProject;
    suite: TreeSuite;
    parentId: number | null;
    siblingIds: number[];
    selected: Selected;
    highlightedSuiteId: number | null;
    depth: number;
    expanded: ReadonlySet<number>;
    canManage: boolean;
    onToggle: (id: number) => void;
}) {
    const isExpanded = expanded.has(suite.id);
    const hasChildren = suite.children.length > 0;
    const isSelected = highlightedSuiteId === suite.id;
    const childSuiteIds = suite.children.map((child) => child.id);
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: suiteItemId(suite.id),
        disabled: !canManage || siblingIds.length < 2,
        data: {
            kind: 'suite',
            id: suite.id,
            name: suite.name,
            parentId,
            container: suiteContainer(parentId),
            order: siblingIds,
        } satisfies DragNode,
    });

    return (
        <li
            ref={setNodeRef}
            style={{
                transform: CSS.Transform.toString(transform),
                transition,
            }}
            className={cn(isDragging && 'opacity-40')}
        >
            <div
                className="group/node relative flex items-center"
                style={{
                    paddingLeft: depth === 0 ? 0 : 20 + (depth - 1) * 12,
                }}
            >
                {canManage && siblingIds.length > 1 && (
                    <span className="absolute top-1/2 left-0 z-10 hidden -translate-y-1/2 group-hover/node:block">
                        <DragHandle
                            label={suite.name}
                            listeners={listeners}
                            attributes={attributes}
                        />
                    </span>
                )}

                {hasChildren ? (
                    <button
                        type="button"
                        onClick={() => onToggle(suite.id)}
                        className="text-text-subtle hover:text-foreground shrink-0 rounded p-0.5"
                        aria-label={isExpanded ? 'Collapse' : 'Expand'}
                        aria-expanded={isExpanded}
                    >
                        <MockIcon
                            name="chev-down"
                            className={cn(
                                'size-3.5',
                                !isExpanded && '-rotate-90',
                            )}
                        />
                    </button>
                ) : null}

                <Link
                    href={suiteShow([project.id, suite.id])}
                    prefetch
                    preserveState
                    preserveScroll
                    className={cn(
                        'flex min-w-0 flex-1 items-center gap-2 rounded-lg px-2.5 py-[7px] text-[13px]',
                        isSelected
                            ? 'bg-primary-50 text-primary-700 font-semibold'
                            : 'hover:bg-muted text-foreground',
                    )}
                >
                    <MockIcon name="folder" className="size-[15px] shrink-0" />
                    <span className="truncate">{suite.name}</span>
                    <span className="text-text-subtle ml-auto text-[11.5px]">
                        {countCases(suite)}
                    </span>
                </Link>
            </div>

            {isExpanded && (
                <ul className="space-y-0.5">
                    <SortableContext
                        items={childSuiteIds.map(suiteItemId)}
                        strategy={verticalListSortingStrategy}
                    >
                        {suite.children.map((child) => (
                            <SuiteNode
                                key={child.id}
                                project={project}
                                suite={child}
                                parentId={suite.id}
                                siblingIds={childSuiteIds}
                                selected={selected}
                                highlightedSuiteId={highlightedSuiteId}
                                depth={depth + 1}
                                expanded={expanded}
                                canManage={canManage}
                                onToggle={onToggle}
                            />
                        ))}
                    </SortableContext>

                </ul>
            )}
        </li>
    );
}

function CaseNode({
    project,
    testCase,
    suiteId,
    siblingIds,
    selected,
    depth,
    canManage,
}: {
    project: SpecificationProject;
    testCase: TreeCase;
    suiteId: number;
    siblingIds: number[];
    selected: Selected;
    depth: number;
    canManage: boolean;
}) {
    const isSelected =
        selected?.type === 'case' && selected.id === testCase.id;
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: caseItemId(testCase.id),
        disabled: !canManage || siblingIds.length < 2,
        data: {
            kind: 'case',
            id: testCase.id,
            name: testCase.name,
            parentId: suiteId,
            container: caseContainer(suiteId),
            order: siblingIds,
        } satisfies DragNode,
    });

    return (
        <li
            ref={setNodeRef}
            style={{
                transform: CSS.Transform.toString(transform),
                transition,
                marginLeft: `${(depth + 1) * 12 + 24}px`,
            }}
            className={cn('flex items-center gap-1', isDragging && 'opacity-40')}
        >
            {canManage && siblingIds.length > 1 && (
                <DragHandle
                    label={testCase.name}
                    listeners={listeners}
                    attributes={attributes}
                />
            )}

            <Link
                href={caseShow([project.id, testCase.id])}
                prefetch
                preserveState
                preserveScroll
                className={cn(
                    'hover:bg-muted flex min-w-0 flex-1 items-center gap-2 rounded px-2 py-1 text-sm',
                    isSelected && 'bg-muted font-medium',
                )}
            >
                <FileText className="text-muted-foreground size-4 shrink-0" />
                <span className="text-muted-foreground shrink-0 text-xs">
                    {testCase.full_external_id}
                </span>
                <span className="truncate">{testCase.name}</span>
            </Link>
        </li>
    );
}

function DragHandle({
    label,
    listeners,
    attributes,
}: {
    label: string;
    listeners: ReturnType<typeof useSortable>['listeners'];
    attributes: ReturnType<typeof useSortable>['attributes'];
}) {
    return (
        <button
            type="button"
            className="text-muted-foreground hover:text-foreground shrink-0 cursor-grab touch-none rounded p-0.5 active:cursor-grabbing"
            aria-label={`Drag to reorder ${label}`}
            {...listeners}
            {...attributes}
        >
            <GripVertical className="size-4" />
        </button>
    );
}
