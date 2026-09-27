import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import TestSuiteController from '@/actions/App/Http/Controllers/TestSpecification/TestSuiteController';
import { MockIcon } from '@/components/chrome/mock-icon';
import { PageHead } from '@/components/chrome/page-head';
import { SegControl } from '@/components/chrome/stat-card';
import CaseDetailPane from '@/components/test-specification/case-detail';
import CasePreview from '@/components/test-specification/case-preview';
import CaseTable from '@/components/test-specification/case-table';
import SuiteDetailPane from '@/components/test-specification/suite-detail';
import SuiteTree from '@/components/test-specification/suite-tree';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { casesForSuite } from '@/lib/specification-tree';
import { cn } from '@/lib/utils';
import { show } from '@/routes/specification';
import { show as caseShow } from '@/routes/specification/cases';
import { show as suiteShow } from '@/routes/specification/suites';
import { create as createCase } from '@/routes/test-cases';
import type { AttachmentRules } from '@/types/attachment';
import type { KeywordFilter, KeywordOption } from '@/types/keyword';
import type { PlatformOption } from '@/types/platform';
import type {
    CaseDetail,
    Selection,
    SpecificationAbilities,
    SpecificationProject,
    TreeSuite,
} from '@/types/test-specification';

type SpecificationPageProps = {
    project: SpecificationProject;
    tree: TreeSuite[];
    can: SpecificationAbilities;
    selected: Selection;
    keywords: KeywordOption[];
    platforms: PlatformOption[];
    keywordFilter: KeywordFilter;
    attachmentRules: AttachmentRules;
};

function previewedCase(selected: Selection): CaseDetail | null {
    if (selected === null) {
        return null;
    }

    return selected.type === 'case' ? selected.case : selected.preview;
}

/**
 * The test specification screen: the project's suite tree beside a pane showing
 * whichever node is selected.
 *
 * Legacy split this across a frameset with the current project held in the
 * session. Here the project and the selected node are both in the URL, so every
 * node is linkable and the browser's history works.
 */
export default function TestSpecificationIndex({
    project,
    tree,
    can,
    selected,
    keywords,
    platforms,
    keywordFilter,
    attachmentRules,
}: SpecificationPageProps) {
    const selectedSuiteName =
        selected === null
            ? null
            : selected.type === 'suite'
              ? selected.suite.name
              : selected.case.suite_name;

    setLayoutProps({
        breadcrumbs: [
            {
                title: 'Test Suites',
                href: show(project.id),
            },
            ...(selectedSuiteName
                ? [
                      {
                          title: selectedSuiteName,
                          href:
                              selected?.type === 'suite'
                                  ? suiteShow([project.id, selected.suite.id])
                                  : selected
                                    ? caseShow([
                                          project.id,
                                          selected.case.id,
                                      ])
                                    : show(project.id),
                      },
                  ]
                : []),
        ],
    });

    const [caseFilter, setCaseFilter] = useState<'all' | 'active' | 'frozen'>(
        'all',
    );
    const [priority, setPriority] = useState<string | null>(null);
    const [addingSuite, setAddingSuite] = useState(false);
    const [pane, setPane] = useState<'preview' | 'case' | 'suite'>('preview');
    const [paneFor, setPaneFor] = useState('');

    const treeSelected = useMemo(() => {
        if (selected === null) {
            return null;
        }

        return {
            type: selected.type,
            id:
                selected.type === 'suite'
                    ? selected.suite.id
                    : selected.case.id,
        };
    }, [selected]);

    const suiteCases = useMemo(() => {
        if (selected === null) {
            return [];
        }

        const suiteId =
            selected.type === 'suite'
                ? selected.suite.id
                : selected.case.test_suite_id;

        return casesForSuite(tree, suiteId);
    }, [selected, tree]);

    const listedCases = useMemo(
        () =>
            suiteCases.filter((item) => {
                if (caseFilter === 'frozen' && item.is_open !== false) {
                    return false;
                }

                if (caseFilter === 'active' && item.is_open === false) {
                    return false;
                }

                if (priority !== null && item.importance !== priority) {
                    return false;
                }

                return true;
            }),
        [suiteCases, caseFilter, priority],
    );

    const shownCase = previewedCase(selected);
    const paneKey = shownCase
        ? `${selected?.type}:${shownCase.id}:${shownCase.version?.version ?? 'none'}`
        : selected?.type === 'suite'
          ? `suite:${selected.suite.id}`
          : '';

    if (paneFor !== paneKey) {
        setPaneFor(paneKey);
        setPane('preview');
    }

    const selectedCaseId =
        selected?.type === 'case'
            ? selected.case.id
            : (shownCase?.id ?? null);

    const activeCount = suiteCases.filter(
        (item) => item.is_open !== false,
    ).length;
    const frozenCount = suiteCases.filter(
        (item) => item.is_open === false,
    ).length;

    return (
        <>
            <Head title={`${project.name} test suites`} />

            <div className="flex min-h-0 flex-1 flex-col p-6">
                <PageHead
                    title="Test Suites"
                    description={`Suites, cases, steps and versions for the ${project.name} project.`}
                    actions={
                        can.manage ? (
                            <Button asChild>
                                <Link
                                    href={createCase.url(project.id, {
                                        query: selected
                                            ? {
                                                  suite:
                                                      selected.type === 'suite'
                                                          ? selected.suite.id
                                                          : selected.case
                                                                .test_suite_id,
                                              }
                                            : {},
                                    })}
                                >
                                    <MockIcon name="plus" />
                                    New case
                                </Link>
                            </Button>
                        ) : undefined
                    }
                />
                <div className="grid min-h-0 flex-1 gap-[18px] lg:grid-cols-[268px_minmax(0,1fr)]">
                    <aside className="bg-card self-start overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                        <div className="flex items-center justify-between border-b px-3.5 py-3">
                            <h2 className="text-[13px] font-bold">Suites</h2>
                            {can.manage && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    type="button"
                                    className="h-auto px-[7px] py-1"
                                    onClick={() =>
                                        setAddingSuite((open) => !open)
                                    }
                                    aria-label="Add test suite"
                                >
                                    <MockIcon name="plus" />
                                </Button>
                            )}
                        </div>
                        {addingSuite && can.manage && (
                            <Form
                                {...TestSuiteController.store.form(project.id)}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="space-y-2 border-b p-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Input
                                            name="name"
                                            placeholder="New top level suite"
                                            aria-label="New top level test suite name"
                                            required
                                        />
                                        <InputError message={errors.name} />
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            Add test suite
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                        <div className="p-2">
                            {keywordFilter.ids.length > 0 &&
                                tree.length === 0 && (
                                    <p className="text-muted-foreground p-2 text-sm">
                                        No test case carries{' '}
                                        {keywordFilter.match === 'all'
                                            ? 'all of those keywords'
                                            : 'any of those keywords'}
                                        .
                                    </p>
                                )}
                            <nav aria-label="Test suites">
                                <SuiteTree
                                    project={project}
                                    suites={tree}
                                    selected={treeSelected}
                                    canManage={can.manage}
                                />
                            </nav>
                        </div>
                    </aside>

                    <div className="grid min-w-0 gap-[18px] xl:grid-cols-[minmax(0,1fr)_380px]">
                        <div className="min-w-0 space-y-4">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <SegControl
                                    items={[
                                        {
                                            label: `All ${suiteCases.length}`,
                                            active: caseFilter === 'all',
                                            onClick: () => setCaseFilter('all'),
                                        },
                                        {
                                            label: `Active ${activeCount}`,
                                            active: caseFilter === 'active',
                                            onClick: () =>
                                                setCaseFilter('active'),
                                        },
                                        {
                                            label: `Frozen ${frozenCount}`,
                                            active: caseFilter === 'frozen',
                                            onClick: () =>
                                                setCaseFilter('frozen'),
                                        },
                                    ]}
                                />
                                <PriorityFilter
                                    value={priority}
                                    onChange={setPriority}
                                />
                                {can.viewKeywords && keywords.length > 0 && (
                                    <KeywordFilterMenu
                                        project={project}
                                        keywords={keywords}
                                        filter={keywordFilter}
                                        selected={selected}
                                    />
                                )}
                                <span className="text-muted-foreground ml-auto text-[12.5px]">
                                    {listedCases.length} cases
                                </span>
                            </div>
                            <div className="bg-card overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                                {selected === null ? (
                                    <p className="text-muted-foreground px-4 py-10 text-center text-sm">
                                        Select a test suite from the tree.
                                    </p>
                                ) : (
                                    <CaseTable
                                        project={project}
                                        cases={listedCases}
                                        selectedId={selectedCaseId}
                                    />
                                )}
                            </div>
                        </div>

                        <aside className="bg-card sticky top-[86px] self-start overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                            {selected === null ? (
                                <p className="text-muted-foreground p-5 text-sm">
                                    Select a suite or case to see its detail.
                                </p>
                            ) : pane === 'case' && shownCase ? (
                                <EditorPane onDone={() => setPane('preview')}>
                                    <CaseDetailPane
                                        key={`${shownCase.id}:${shownCase.version?.version ?? 'none'}`}
                                        project={project}
                                        tree={tree}
                                        testCase={shownCase}
                                        can={can}
                                        keywords={keywords}
                                        platforms={platforms}
                                        attachmentRules={attachmentRules}
                                    />
                                </EditorPane>
                            ) : pane === 'suite' &&
                              selected.type === 'suite' ? (
                                <EditorPane onDone={() => setPane('preview')}>
                                    <SuiteDetailPane
                                        key={selected.suite.id}
                                        project={project}
                                        tree={tree}
                                        suite={selected.suite}
                                        can={can}
                                        keywords={keywords}
                                        attachmentRules={attachmentRules}
                                    />
                                </EditorPane>
                            ) : shownCase ? (
                                <CasePreview
                                    key={`${shownCase.id}:${shownCase.version?.version ?? 'none'}`}
                                    project={project}
                                    testCase={shownCase}
                                    can={can}
                                    onEdit={() => setPane('case')}
                                    onEditSuite={
                                        selected.type === 'suite'
                                            ? () => setPane('suite')
                                            : undefined
                                    }
                                />
                            ) : selected.type === 'suite' ? (
                                <EditorPane onDone={() => setPane('preview')}>
                                    <SuiteDetailPane
                                        key={selected.suite.id}
                                        project={project}
                                        tree={tree}
                                        suite={selected.suite}
                                        can={can}
                                        keywords={keywords}
                                        attachmentRules={attachmentRules}
                                    />
                                </EditorPane>
                            ) : null}
                        </aside>
                    </div>
                </div>
            </div>
        </>
    );
}

function EditorPane({
    children,
    onDone,
}: {
    children: ReactNode;
    onDone: () => void;
}) {
    return (
        <div className="p-4">
            <div className="mb-3 flex justify-end">
                <Button variant="outline" size="sm" type="button" onClick={onDone}>
                    Done
                </Button>
            </div>
            {children}
        </div>
    );
}

function PriorityFilter({
    value,
    onChange,
}: {
    value: string | null;
    onChange: (value: string | null) => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((current) => !current)}
                className={cn(
                    'inline-flex items-center gap-2 rounded-lg border px-3 py-[7px] text-[13px] font-medium',
                    value
                        ? 'border-primary bg-primary-50 text-primary-700'
                        : 'border-border bg-card text-muted-foreground',
                )}
            >
                <MockIcon name="flag" className="size-[15px]" />
                Priority
                <MockIcon name="chev-down" className="size-3.5" />
            </button>
            {open && (
                <div className="bg-card absolute top-full z-20 mt-1 min-w-[140px] rounded-lg border py-1 shadow-[0_6px_20px_rgba(16,24,40,.08)]">
                    {[
                        [null, 'Any'],
                        ['high', 'High'],
                        ['medium', 'Medium'],
                        ['low', 'Low'],
                    ].map(([key, label]) => (
                        <button
                            key={label}
                            type="button"
                            className="hover:bg-muted block w-full px-3 py-1.5 text-left text-[13px]"
                            onClick={() => {
                                onChange(key);
                                setOpen(false);
                            }}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function KeywordFilterMenu({
    project,
    keywords,
    filter,
    selected,
}: {
    project: SpecificationProject;
    keywords: KeywordOption[];
    filter: KeywordFilter;
    selected: Selection;
}) {
    const [open, setOpen] = useState(false);

    const href = (query: {
        keywords: number[];
        keyword_match: 'any' | 'all' | null;
    }) => {
        if (selected === null) {
            return show(project.id, { query });
        }

        return selected.type === 'suite'
            ? suiteShow([project.id, selected.suite.id], { query })
            : caseShow([project.id, selected.case.id], { query });
    };

    const match = filter.match === 'all' ? 'all' : null;
    const toggled = (id: number) =>
        filter.ids.includes(id)
            ? filter.ids.filter((each) => each !== id)
            : [...filter.ids, id];

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen((current) => !current)}
                className={cn(
                    'inline-flex items-center gap-2 rounded-lg border px-3 py-[7px] text-[13px] font-medium',
                    filter.ids.length > 0
                        ? 'border-primary bg-primary-50 text-primary-700'
                        : 'border-border bg-card text-muted-foreground',
                )}
            >
                <MockIcon name="tag" className="size-[15px]" />
                Keywords
                {filter.ids.length > 0 ? ` · ${filter.ids.length}` : ''}
                <MockIcon name="chev-down" className="size-3.5" />
            </button>
            {open && (
                <div className="bg-card absolute top-full z-20 mt-1 min-w-[200px] rounded-lg border py-1 shadow-[0_6px_20px_rgba(16,24,40,.08)]">
                    <Link
                        href={href({ keywords: [], keyword_match: null })}
                        preserveState
                        preserveScroll
                        className="hover:bg-muted block px-3 py-1.5 text-[13px]"
                    >
                        Any
                    </Link>
                    {keywords.map((keyword) => {
                        const on = filter.ids.includes(keyword.id);

                        return (
                            <Link
                                key={keyword.id}
                                href={href({
                                    keywords: toggled(keyword.id),
                                    keyword_match: match,
                                })}
                                preserveState
                                preserveScroll
                                className={cn(
                                    'block px-3 py-1.5 text-[13px]',
                                    on
                                        ? 'bg-primary-50 text-primary-700 font-semibold'
                                        : 'hover:bg-muted',
                                )}
                            >
                                {keyword.name}
                            </Link>
                        );
                    })}
                    {filter.ids.length > 1 && (
                        <div className="border-border mt-1 flex gap-2 border-t px-3 py-2 text-[12px]">
                            {(['any', 'all'] as const).map((mode) => (
                                <Link
                                    key={mode}
                                    href={href({
                                        keywords: filter.ids,
                                        keyword_match:
                                            mode === 'all' ? 'all' : null,
                                    })}
                                    preserveState
                                    preserveScroll
                                    className={cn(
                                        'rounded px-1.5 py-0.5',
                                        filter.match === mode
                                            ? 'bg-muted font-semibold'
                                            : 'hover:bg-muted text-muted-foreground',
                                    )}
                                >
                                    {mode === 'any' ? 'any' : 'all'}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
