import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import TestCaseController from '@/actions/App/Http/Controllers/TestSpecification/TestCaseController';
import TestCaseVersionController from '@/actions/App/Http/Controllers/TestSpecification/TestCaseVersionController';
import { MockIcon } from '@/components/chrome/mock-icon';
import {
    PriorityMark,
    StatusPill,
    Tag,
    UserAvatar,
} from '@/components/chrome/stat-card';
import RichText from '@/components/rich-text/rich-text';
import { Button } from '@/components/ui/button';
import { index as selectorIndex } from '@/routes/plan-selector';
import { importanceLabels } from '@/types/test-specification';
import type {
    CaseDetail,
    SpecificationAbilities,
    SpecificationProject,
} from '@/types/test-specification';

function lastRunLabel(status: string): string {
    return status
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

/**
 * The read-only case card the mockup's right pane shows.
 *
 * Edit, copy, freeze and delete live here as one-click actions; the card
 * itself never hosts the authoring form, so it cannot nest the attachment
 * and reorder forms.
 */
export default function CasePreview({
    project,
    testCase,
    can,
    onEdit,
    onEditSuite,
}: {
    project: SpecificationProject;
    testCase: CaseDetail;
    can: SpecificationAbilities;
    onEdit: () => void;
    onEditSuite?: () => void;
}) {
    const version = testCase.version;
    const script = version?.script_links[0] ?? null;
    const filename = script?.path.split('/').pop() ?? null;
    const coverage = script?.path.includes('/')
        ? script.path.replace(/\/[^/]+$/, '')
        : null;
    const owner = version?.author ?? version?.updater;
    const steps = version?.steps ?? [];
    const lastRun = testCase.last_run;
    const frozen = version !== null && !version.is_open;
    const [moreOpen, setMoreOpen] = useState(false);

    return (
        <>
            <div className="flex items-start gap-2.5 border-b px-[18px] py-4">
                <div className="min-w-0">
                    <span className="text-muted-foreground font-mono text-xs">
                        {testCase.full_external_id}
                        {version ? ` · v${version.version}` : ''}
                    </span>
                    <h3 className="mt-0.5 text-[15px] font-bold">
                        {testCase.name}
                    </h3>
                </div>
                <div className="relative ml-auto">
                    <button
                        type="button"
                        className="text-muted-foreground hover:bg-muted rounded-md p-[5px]"
                        aria-label="More"
                        aria-expanded={moreOpen}
                        onClick={() => setMoreOpen((open) => !open)}
                    >
                        <MockIcon name="more" />
                    </button>
                    {moreOpen && (
                        <div className="bg-card absolute top-full right-0 z-20 mt-1 min-w-[160px] rounded-lg border py-1 shadow-[0_6px_20px_rgba(16,24,40,.08)]">
                            <button
                                type="button"
                                className="hover:bg-muted block w-full px-3 py-1.5 text-left text-[13px]"
                                onClick={() => {
                                    setMoreOpen(false);
                                    onEdit();
                                }}
                            >
                                Edit case
                            </button>
                            {onEditSuite && (
                                <button
                                    type="button"
                                    className="hover:bg-muted block w-full px-3 py-1.5 text-left text-[13px]"
                                    onClick={() => {
                                        setMoreOpen(false);
                                        onEditSuite();
                                    }}
                                >
                                    Edit suite
                                </button>
                            )}
                            {can.manage && version && (
                                <Form
                                    {...TestCaseVersionController.store.form(
                                        testCase.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                >
                                    <button
                                        type="submit"
                                        className="hover:bg-muted block w-full px-3 py-1.5 text-left text-[13px]"
                                    >
                                        New version
                                    </button>
                                </Form>
                            )}
                            {can.freeze && version && (
                                <Form
                                    {...(frozen
                                        ? TestCaseVersionController.unfreeze.form(
                                              version.id,
                                          )
                                        : TestCaseVersionController.freeze.form(
                                              version.id,
                                          ))}
                                    options={{ preserveScroll: true }}
                                >
                                    <button
                                        type="submit"
                                        className="hover:bg-muted block w-full px-3 py-1.5 text-left text-[13px]"
                                    >
                                        {frozen ? 'Reopen version' : 'Freeze version'}
                                    </button>
                                </Form>
                            )}
                            {can.manage && (
                                <Form
                                    {...TestCaseController.destroy.form(
                                        testCase.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                    onSubmit={(event) => {
                                        if (
                                            !confirm(
                                                `Delete ${testCase.full_external_id}?`,
                                            )
                                        ) {
                                            event.preventDefault();
                                        }
                                    }}
                                >
                                    <button
                                        type="submit"
                                        className="text-destructive hover:bg-muted block w-full px-3 py-1.5 text-left text-[13px]"
                                    >
                                        Delete case
                                    </button>
                                </Form>
                            )}
                        </div>
                    )}
                </div>
            </div>

            <div className="p-[18px]">
                <div className="mb-3.5 flex flex-wrap items-center gap-2">
                    {version && (
                        <PriorityMark
                            priority={
                                importanceLabels[version.importance] ??
                                version.importance
                            }
                        />
                    )}
                    {testCase.keywords[0] && (
                        <Tag>{testCase.keywords[0].name}</Tag>
                    )}
                    {lastRun && (
                        <StatusPill
                            status={lastRun}
                            label={`Last run: ${lastRunLabel(lastRun)}`}
                            className="normal-case"
                        />
                    )}
                </div>

                <dl className="mb-4 grid grid-cols-[96px_1fr] gap-x-3 gap-y-2 text-[13px]">
                    <dt className="text-muted-foreground">Suite</dt>
                    <dd>{testCase.suite_name}</dd>
                    <dt className="text-muted-foreground">Owner</dt>
                    <dd className="flex items-center gap-1.5">
                        {owner ? (
                            <>
                                <UserAvatar name={owner} size="sm" />
                                {owner}
                            </>
                        ) : (
                            '—'
                        )}
                    </dd>
                    <dt className="text-muted-foreground">Updated</dt>
                    <dd>{testCase.updated ?? '—'}</dd>
                    <dt className="text-muted-foreground">Automation</dt>
                    <dd>
                        {filename ? (
                            <span className="text-primary-700 flex items-center gap-1.5">
                                <MockIcon name="git" className="size-[13px]" />
                                {script?.url ? (
                                    <a
                                        href={script.url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        {filename}
                                    </a>
                                ) : (
                                    filename
                                )}
                            </span>
                        ) : (
                            'Manual'
                        )}
                    </dd>
                </dl>

                <div className="bg-border mb-3.5 h-px" />

                <div className="mb-2 text-[13px] font-bold">Preconditions</div>
                <RichText
                    html={version?.preconditions ?? null}
                    empty="No preconditions."
                    className="text-muted-foreground mb-3.5 text-[13.5px] [&_code]:font-mono"
                />

                <div className="mb-1.5 flex items-center justify-between">
                    <strong className="text-[13px]">Steps</strong>
                    <span className="text-text-subtle text-xs">
                        {steps.length} {steps.length === 1 ? 'step' : 'steps'}
                    </span>
                </div>

                {steps.map((step, index) => (
                    <div
                        key={step.id}
                        className="flex gap-3 border-b border-dashed py-3 last:border-b-0"
                    >
                        <span className="bg-primary-50 text-primary-700 grid size-6 shrink-0 place-items-center rounded-full text-xs font-bold">
                            {step.sort_order || index + 1}
                        </span>
                        <div className="min-w-0 flex-1">
                            <div className="text-text-subtle mb-1 text-[11px] font-semibold tracking-[0.04em] uppercase">
                                Action
                            </div>
                            <RichText
                                html={step.actions}
                                empty=""
                                className="text-[13.5px] [&_code]:font-mono"
                            />
                            <div className="text-text-subtle mt-2 mb-1 text-[11px] font-semibold tracking-[0.04em] uppercase">
                                Expected
                            </div>
                            <RichText
                                html={step.expected_results}
                                empty=""
                                className="text-muted-foreground text-[13.5px] [&_code]:font-mono"
                            />
                        </div>
                    </div>
                ))}

                {script && (
                    <>
                        <div className="bg-border my-3.5 h-px" />
                        <div className="border-border-strong bg-muted text-muted-foreground rounded-xl border border-dashed px-3.5 py-3 text-[12.5px]">
                            <MockIcon
                                name="git"
                                className="mr-1 inline size-[13px] align-[-2px]"
                            />
                            Automated by <strong>{filename}</strong>
                            {script.repository ? (
                                <>
                                    {' '}
                                    in{' '}
                                    <span className="font-mono">
                                        {script.repository}
                                    </span>
                                </>
                            ) : null}
                            {coverage ? (
                                <>
                                    {' '}
                                    — covers{' '}
                                    <span className="font-mono">{coverage}</span>
                                </>
                            ) : null}
                        </div>
                    </>
                )}

                <div className="mt-4 flex gap-2">
                    {can.manage && (
                        <Button size="sm" className="flex-1" onClick={onEdit}>
                            <MockIcon name="edit" />
                            Edit
                        </Button>
                    )}
                    <Button
                        size="sm"
                        variant="outline"
                        className="flex-1"
                        asChild
                    >
                        <Link href={selectorIndex(project.id)}>
                            <MockIcon name="play" />
                            Run
                        </Link>
                    </Button>
                    {can.manage && (
                        <Form
                            {...TestCaseController.copy.form(testCase.id)}
                            options={{ preserveScroll: true }}
                        >
                            <input
                                type="hidden"
                                name="test_suite_id"
                                value={testCase.test_suite_id}
                            />
                            <Button
                                size="sm"
                                variant="outline"
                                type="submit"
                                aria-label="Copy case"
                            >
                                <MockIcon name="copy" />
                            </Button>
                        </Form>
                    )}
                </div>
            </div>
        </>
    );
}
