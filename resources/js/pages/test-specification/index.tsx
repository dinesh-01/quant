import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { Monitor, Plus, Tag } from 'lucide-react';
import { useMemo } from 'react';
import TestSuiteController from '@/actions/App/Http/Controllers/TestSpecification/TestSuiteController';
import CaseDetailPane from '@/components/test-specification/case-detail';
import CaseSearch from '@/components/test-specification/case-search';
import KeywordFilterPanel from '@/components/test-specification/keyword-filter';
import SuiteDetailPane from '@/components/test-specification/suite-detail';
import SuiteTree from '@/components/test-specification/suite-tree';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { index as keywordIndex } from '@/routes/keywords';
import { index as platformIndex } from '@/routes/platforms';
import { show } from '@/routes/specification';
import type { AttachmentRules } from '@/types/attachment';
import type { KeywordFilter, KeywordOption } from '@/types/keyword';
import type { PlatformOption } from '@/types/platform';
import type {
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
    setLayoutProps({
        breadcrumbs: [
            {
                title: `${project.name} specification`,
                href: show(project.id),
            },
        ],
    });

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

    return (
        <>
            <Head title={`${project.name} specification`} />

            <div className="flex min-h-0 flex-1 flex-col gap-4 p-4 lg:flex-row">
                <aside className="w-full shrink-0 space-y-4 lg:w-80">
                    <div>
                        <h2 className="text-sm font-medium">{project.name}</h2>

                        <p className="text-muted-foreground text-xs">
                            {project.prefix}
                        </p>
                    </div>

                    <CaseSearch project={project} />

                    <KeywordFilterPanel
                        project={project}
                        keywords={keywords}
                        filter={keywordFilter}
                        selected={selected}
                    />

                    {can.viewKeywords && (
                        <Link
                            href={keywordIndex(project.id)}
                            className="text-muted-foreground hover:text-foreground flex items-center gap-1.5 text-xs"
                        >
                            <Tag className="size-3.5" />
                            {keywords.length === 0
                                ? 'Add keywords'
                                : 'Manage keywords'}
                        </Link>
                    )}

                    {can.viewPlatforms && (
                        <Link
                            href={platformIndex(project.id)}
                            className="text-muted-foreground hover:text-foreground flex items-center gap-1.5 text-xs"
                        >
                            <Monitor className="size-3.5" />
                            {platforms.length === 0
                                ? 'Add platforms'
                                : 'Manage platforms'}
                        </Link>
                    )}

                    <Separator />

                    {keywordFilter.ids.length > 0 && tree.length === 0 && (
                        <p className="text-muted-foreground text-sm">
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

                    {can.manage && (
                        <>
                            <Separator />

                            <Form
                                {...TestSuiteController.store.form(project.id)}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="space-y-2"
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
                                            <Plus className="size-4" />
                                            Add test suite
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </>
                    )}
                </aside>

                <Separator className="lg:hidden" />

                <main className="min-w-0 flex-1">
                    {selected === null ? (
                        <p className="text-muted-foreground text-sm">
                            Select a test suite or test case from the tree.
                        </p>
                    ) : selected.type === 'suite' ? (
                        /*
                         * Keyed by the node so selecting another one remounts
                         * the pane. Both panes edit through uncontrolled inputs,
                         * and a `defaultValue` is only read when the input
                         * mounts — without the key, React would reuse the same
                         * inputs and keep showing the previous node's text.
                         * The case pane is keyed by version too, because the
                         * version switcher changes the same fields.
                         */
                        <SuiteDetailPane
                            key={selected.suite.id}
                            project={project}
                            tree={tree}
                            suite={selected.suite}
                            can={can}
                            keywords={keywords}
                            attachmentRules={attachmentRules}
                        />
                    ) : (
                        <CaseDetailPane
                            key={`${selected.case.id}:${selected.case.version?.version ?? 'none'}`}
                            project={project}
                            tree={tree}
                            testCase={selected.case}
                            can={can}
                            keywords={keywords}
                            platforms={platforms}
                            attachmentRules={attachmentRules}
                        />
                    )}
                </main>
            </div>
        </>
    );
}
