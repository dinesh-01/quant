import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AttachmentController from '@/actions/App/Http/Controllers/Attachments/AttachmentController';
import ExecutionController from '@/actions/App/Http/Controllers/Executions/ExecutionController';
import ExecutionIssueController from '@/actions/App/Http/Controllers/Executions/ExecutionIssueController';
import ExecutionIssueFromFailureController from '@/actions/App/Http/Controllers/Executions/ExecutionIssueFromFailureController';
import AttachmentList from '@/components/attachments/attachment-list';
import CustomFieldInputs from '@/components/custom-fields/custom-field-inputs';
import { MockIcon } from '@/components/chrome/mock-icon';
import { PriorityMark, StatusPill, Tag } from '@/components/chrome/stat-card';
import { RunCaseList } from '@/components/executions/run-case-list';
import { RunFilters } from '@/components/executions/run-filters';
import { RunWorkspaceHead } from '@/components/executions/run-workspace-head';
import { useRunFilters } from '@/components/executions/use-run-filters';
import InputError from '@/components/input-error';
import RichText from '@/components/rich-text/rich-text';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { index as executeIndex, show as executeShow } from '@/routes/executions';
import { index as selectorIndex } from '@/routes/plan-selector';
import { show as caseShow } from '@/routes/specification/cases';
import type { AttachmentRules, AttachmentSummary } from '@/types/attachment';
import type { CustomFieldInput } from '@/types/custom-field';
import type {
    ExecutionBuild,
    ExecutionListItem,
    ExecutionPlan,
    ExecutionRecord,
    ExecutionShowItem,
    RunCounts,
} from '@/types/execution';
import { cn } from '@/lib/utils';

type ExecutionShowProps = {
    project: { id: number; name: string };
    plan: ExecutionPlan;
    build: { id: number; name: string; is_open: boolean };
    builds?: ExecutionBuild[];
    openIssue?: boolean;
    item: ExecutionShowItem;
    draft: ExecutionRecord | null;
    draftId: number | null;
    history: ExecutionRecord[];
    customFields: CustomFieldInput[];
    attachments: AttachmentSummary[];
    attachmentRules: AttachmentRules;
    issueTracker: { enabled: boolean; name: string | null };
    can: { execute: boolean; delete: boolean; editNotes: boolean };
    items: ExecutionListItem[];
    counts: RunCounts;
};

function stepResults(
    item: ExecutionShowItem,
    draft: ExecutionRecord | null,
): Record<number, string> {
    return Object.fromEntries(
        item.steps.map((step) => [
            step.id,
            draft?.steps.find((result) => result.test_case_step_id === step.id)
                ?.status ?? 'not_run',
        ]),
    );
}

function stepNoteValues(
    item: ExecutionShowItem,
    draft: ExecutionRecord | null,
): Record<number, string> {
    return Object.fromEntries(
        item.steps.map((step) => [
            step.id,
            draft?.steps.find((result) => result.test_case_step_id === step.id)
                ?.notes ?? '',
        ]),
    );
}

const verdictStyle: Record<string, string> = {
    passed: 'border-success bg-success-bg text-success',
    failed: 'border-destructive bg-destructive-bg text-destructive',
    blocked: 'border-warning bg-warning-bg text-warning',
    not_run: 'border-neutral-border bg-neutral-bg text-neutral',
};

export default function ExecutionShow({
    project,
    plan,
    build,
    builds = [],
    openIssue = false,
    item,
    draft,
    draftId,
    history,
    customFields,
    attachments,
    attachmentRules,
    issueTracker,
    can,
    items = [],
    counts = { passed: 0, failed: 0, blocked: 0, not_run: 0 },
}: ExecutionShowProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Execution', href: selectorIndex(project.id) },
            {
                title: `${plan.external_id} ${plan.name}`,
                href: executeIndex.url(plan.id, {
                    query: { build: build.id },
                }),
            },
            {
                title: 'Run',
                href: executeShow.url([plan.id, item.id], {
                    query: { build: build.id },
                }),
            },
        ],
    });

    const rail = useRunFilters(items);
    const [verdict, setVerdict] = useState(draft?.status ?? 'not_run');
    const [stepStatus, setStepStatus] = useState<Record<number, string>>(() =>
        stepResults(item, draft),
    );
    const [stepNotes, setStepNotes] = useState<Record<number, string>>(() =>
        stepNoteValues(item, draft),
    );
    const [issueOpen, setIssueOpen] = useState(openIssue);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setVerdict(draft?.status ?? 'not_run');
        setStepStatus(stepResults(item, draft));
        setStepNotes(stepNoteValues(item, draft));
        setIssueOpen(openIssue);
    }, [item.id, draft, openIssue, item]);
    const elapsed = useElapsed(draft?.duration ?? null);
    const remaining = rail.visible.filter(
        (row) => row.latest_status === null,
    ).length;
    const position = items.findIndex((row) => row.id === item.id) + 1;
    const currentStepId = useMemo(() => {
        const open = item.steps.find(
            (step) => (stepStatus[step.id] ?? 'not_run') !== 'passed',
        );

        return open?.id ?? item.steps.at(-1)?.id ?? null;
    }, [item.steps, stepStatus]);
    const latestCompleted = history[0] ?? null;
    const canLinkIssue =
        latestCompleted !== null &&
        (latestCompleted.status === 'failed' ||
            latestCompleted.status === 'blocked');

    const applyStepStatus = (stepId: number, status: string): void => {
        setStepStatus((current) => {
            const next = { ...current, [stepId]: status };
            const values = item.steps.map(
                (step) => next[step.id] ?? 'not_run',
            );

            if (values.some((value) => value === 'failed')) {
                setVerdict('failed');
            } else if (
                values.length > 0 &&
                values.every((value) => value === 'passed')
            ) {
                setVerdict('passed');
            }

            return next;
        });
    };

    return (
        <>
            <Head title={`Run ${item.full_external_id}`} />

            <div className="flex flex-1 flex-col p-6">
                <RunWorkspaceHead
                    title={`${plan.name} — ${build.name}`}
                    position={position > 0 ? position : 1}
                    total={items.length}
                    assignedToViewer={item.assigned_to_viewer}
                    platform={item.platform}
                    counts={counts}
                    projectId={project.id}
                    buildName={build.name}
                />

                {builds.length > 1 && (
                    <div className="mb-4 flex flex-wrap items-center gap-2.5">
                        {builds.map((each) => (
                            <Link
                                key={each.id}
                                href={executeShow.url([plan.id, item.id], {
                                    query: { build: each.id },
                                })}
                                className={
                                    each.id === build.id
                                        ? 'border-primary bg-primary-50 text-primary-700 inline-flex items-center rounded-lg border px-3 py-1.5 text-[13px] font-semibold'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted inline-flex items-center rounded-lg border px-3 py-1.5 text-[13px] font-medium'
                                }
                            >
                                {each.name}
                                {!each.is_open && ' (closed)'}
                            </Link>
                        ))}
                    </div>
                )}

                <RunFilters
                    filters={rail.filters}
                    options={rail.options}
                    onChange={rail.setFilters}
                    onClear={rail.clear}
                />

                <div className="grid grid-cols-1 gap-[18px] md:grid-cols-[300px_1fr]">
                    <RunCaseList
                        planId={plan.id}
                        buildId={build.id}
                        items={rail.visible}
                        activeId={item.id}
                        remaining={remaining}
                    />

                    <div className="min-w-0">
                        <div className="bg-card mb-4 overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                            <div className="flex flex-wrap items-start justify-between gap-3 border-b px-[18px] py-4">
                                <div>
                                    <p className="text-text-subtle font-mono text-xs">
                                        {item.full_external_id} · v{item.version}
                                    </p>
                                    <h2 className="mt-0.5 text-[15px] font-bold">
                                        {item.name}
                                    </h2>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <PriorityMark priority={item.priority} />
                                    {item.keywords.map((keyword) => (
                                        <Tag key={keyword}>{keyword}</Tag>
                                    ))}
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                className="px-[7px]"
                                                aria-label="More case actions"
                                            >
                                                <MockIcon name="more" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href={caseShow.url([
                                                        project.id,
                                                        item.test_case_id,
                                                    ])}
                                                >
                                                    Open in specification
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href={executeIndex.url(
                                                        plan.id,
                                                        {
                                                            query: {
                                                                build: build.id,
                                                            },
                                                        },
                                                    )}
                                                >
                                                    Complete selected…
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    void navigator.clipboard.writeText(
                                                        item.full_external_id,
                                                    )
                                                }
                                            >
                                                Copy case id
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>

                            <div className="space-y-4 p-[18px]">
                                {item.preconditions && (
                                    <div className="bg-muted border-border-strong rounded-xl border border-dashed px-3.5 py-3 text-[12.5px]">
                                        <strong>Preconditions:</strong>{' '}
                                        <RichText html={item.preconditions} />
                                    </div>
                                )}

                                {can.execute ? (
                                    <Form
                                        id="run-form"
                                        {...ExecutionController.store.form(
                                            item.id,
                                        )}
                                        options={{
                                            preserveScroll: true,
                                            onStart: () => setSaving(true),
                                            onFinish: () => setSaving(false),
                                        }}
                                        className="space-y-3"
                                    >
                                        {({ errors }) => (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="build_id"
                                                    value={build.id}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="status"
                                                    value={verdict}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="duration"
                                                    value={elapsed.minutes}
                                                />

                                                {item.steps.map((step, index) => {
                                                    const result =
                                                        draft?.steps.find(
                                                            (row) =>
                                                                row.test_case_step_id ===
                                                                step.id,
                                                        );
                                                    const status =
                                                        stepStatus[step.id] ??
                                                        'not_run';
                                                    const current =
                                                        step.id ===
                                                        currentStepId;

                                                    return (
                                                        <div
                                                            key={step.id}
                                                            className={cn(
                                                                'mb-3 overflow-hidden rounded-xl border last:mb-0',
                                                                current &&
                                                                    'border-primary-100',
                                                            )}
                                                        >
                                                            <input
                                                                type="hidden"
                                                                name={`steps[${index}][test_case_step_id]`}
                                                                value={step.id}
                                                            />
                                                            <input
                                                                type="hidden"
                                                                name={`steps[${index}][status]`}
                                                                value={status}
                                                            />
                                                            <div
                                                                className={cn(
                                                                    'flex items-center gap-3 px-4 py-3',
                                                                    current
                                                                        ? 'bg-primary-50'
                                                                        : 'bg-muted',
                                                                )}
                                                            >
                                                                <span
                                                                    className={cn(
                                                                        'grid size-6 place-items-center rounded-full text-xs font-bold',
                                                                        status ===
                                                                            'passed'
                                                                            ? 'bg-success-bg text-success'
                                                                            : status ===
                                                                                'failed'
                                                                              ? 'bg-destructive-bg text-destructive'
                                                                              : 'bg-primary-50 text-primary-700',
                                                                    )}
                                                                >
                                                                    {
                                                                        step.sort_order
                                                                    }
                                                                </span>
                                                                <strong className="min-w-0 flex-1 text-[13.5px]">
                                                                    <RichText
                                                                        html={
                                                                            step.actions
                                                                        }
                                                                        empty="No action."
                                                                    />
                                                                </strong>
                                                                <div className="flex gap-1.5">
                                                                    <MiniVerdict
                                                                        tone="pass"
                                                                        pressed={
                                                                            status ===
                                                                            'passed'
                                                                        }
                                                                        onClick={() =>
                                                                            applyStepStatus(
                                                                                step.id,
                                                                                'passed',
                                                                            )
                                                                        }
                                                                    />
                                                                    <MiniVerdict
                                                                        tone="fail"
                                                                        pressed={
                                                                            status ===
                                                                            'failed'
                                                                        }
                                                                        onClick={() =>
                                                                            applyStepStatus(
                                                                                step.id,
                                                                                'failed',
                                                                            )
                                                                        }
                                                                    />
                                                                </div>
                                                            </div>
                                                            <div className="grid gap-4 p-4 sm:grid-cols-2">
                                                                <div>
                                                                    <p className="text-text-subtle mb-1 text-[11px] font-semibold tracking-[0.04em] uppercase">
                                                                        Expected
                                                                    </p>
                                                                    <RichText
                                                                        className="text-muted-foreground"
                                                                        html={
                                                                            step.expected_results
                                                                        }
                                                                        empty="None."
                                                                    />
                                                                </div>
                                                                <div>
                                                                    <p className="text-text-subtle mb-1 text-[11px] font-semibold tracking-[0.04em] uppercase">
                                                                        {current
                                                                            ? 'Actual — add note'
                                                                            : 'Actual'}
                                                                    </p>
                                                                    <input
                                                                        type="hidden"
                                                                        name={`steps[${index}][notes]`}
                                                                        value={
                                                                            stepNotes[
                                                                                step
                                                                                    .id
                                                                            ] ??
                                                                            ''
                                                                        }
                                                                    />
                                                                    {current ? (
                                                                        <textarea
                                                                            value={
                                                                                stepNotes[
                                                                                    step
                                                                                        .id
                                                                                ] ??
                                                                                ''
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                setStepNotes(
                                                                                    (
                                                                                        notes,
                                                                                    ) => ({
                                                                                        ...notes,
                                                                                        [step.id]:
                                                                                            event
                                                                                                .target
                                                                                                .value,
                                                                                    }),
                                                                                )
                                                                            }
                                                                            placeholder="What did you observe?"
                                                                            className="border-border w-full resize-y rounded-lg border px-2 py-2 font-sans text-[13px]"
                                                                            rows={2}
                                                                        />
                                                                    ) : (
                                                                        <p className="text-[13.5px]">
                                                                            {stepNotes[
                                                                                step
                                                                                    .id
                                                                            ] ||
                                                                                result?.notes ||
                                                                                '—'}
                                                                        </p>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    );
                                                })}

                                                <CustomFieldInputs
                                                    fields={customFields}
                                                    errors={errors}
                                                />
                                                <InputError
                                                    message={errors.status}
                                                />
                                                <textarea
                                                    id="notes"
                                                    name="notes"
                                                    defaultValue={
                                                        draft?.notes ?? ''
                                                    }
                                                    rows={2}
                                                    placeholder="Notes"
                                                    className="border-border w-full rounded-lg border px-3 py-2 text-sm"
                                                />
                                            </>
                                        )}
                                    </Form>
                                ) : (
                                    <p className="text-muted-foreground text-sm">
                                        This plan or build is closed, or you may
                                        only inspect results.
                                    </p>
                                )}

                                <div>
                                    <p className="mb-2 text-[13px] font-bold">
                                        Evidence
                                    </p>
                                    {draftId !== null && can.execute ? (
                                        <AttachmentList
                                            attachments={attachments}
                                            rules={attachmentRules}
                                            upload={AttachmentController.storeForExecution.form(
                                                draftId,
                                            )}
                                            canManage
                                            describedAs="this run"
                                        />
                                    ) : can.execute ? (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="text-muted-foreground text-[12.5px]">
                                                Save a draft to attach a
                                                screenshot.
                                            </p>
                                            <Button
                                                form="run-form"
                                                size="sm"
                                                variant="outline"
                                                disabled={saving}
                                            >
                                                Save draft
                                            </Button>
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground text-[12.5px]">
                                            Evidence is available after a draft
                                            is saved.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>

                        {can.execute && (
                            <div className="bg-card rounded-xl border p-[18px] shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                                <div className="mb-3 flex items-center justify-between">
                                    <strong className="text-[13.5px]">
                                        Case verdict
                                    </strong>
                                    <span className="text-text-subtle inline-flex items-center gap-1 text-xs">
                                        <MockIcon
                                            name="clock"
                                            className="size-[13px]"
                                        />
                                        {elapsed.label}
                                    </span>
                                </div>
                                <div className="flex gap-2.5">
                                    {(
                                        [
                                            ['passed', 'Pass', 'check'],
                                            ['failed', 'Fail', 'x'],
                                            ['blocked', 'Blocked', 'lock'],
                                            ['not_run', 'Skip', 'arrow-right'],
                                        ] as const
                                    ).map(([value, label, icon]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            onClick={() => setVerdict(value)}
                                            className={cn(
                                                'flex flex-1 flex-col items-center gap-1 rounded-xl border-[1.5px] px-3 py-3 text-[13px] font-bold',
                                                verdict === value
                                                    ? verdictStyle[value]
                                                    : 'border-border bg-card hover:border-border-strong',
                                            )}
                                        >
                                            <MockIcon
                                                name={icon}
                                                className="size-[22px]"
                                            />
                                            {label}
                                        </button>
                                    ))}
                                </div>
                                <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setIssueOpen((open) => !open)}
                                    >
                                        <MockIcon name="bug" />
                                        Link / create issue
                                    </Button>
                                    <div className="flex gap-2">
                                        <Button
                                            form="run-form"
                                            disabled={saving}
                                        >
                                            Save draft
                                        </Button>
                                        <Button
                                            form="run-form"
                                            name="complete"
                                            value="1"
                                            disabled={saving}
                                        >
                                            {verdict === 'not_run'
                                                ? 'Skip & next'
                                                : 'Submit & next'}
                                            <MockIcon name="arrow-right" />
                                        </Button>
                                    </div>
                                </div>

                                {issueOpen && (
                                    <div className="mt-4 space-y-3 border-t pt-4">
                                        {canLinkIssue && latestCompleted ? (
                                            <>
                                                {issueTracker.enabled && (
                                                    <Form
                                                        {...ExecutionIssueFromFailureController.store.form(
                                                            latestCompleted.id,
                                                        )}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                    >
                                                        <Button
                                                            size="sm"
                                                            variant="secondary"
                                                        >
                                                            Create{' '}
                                                            {issueTracker.name ??
                                                                'tracker'}{' '}
                                                            issue
                                                        </Button>
                                                    </Form>
                                                )}
                                                <Form
                                                    {...ExecutionIssueController.store.form(
                                                        latestCompleted.id,
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                    resetOnSuccess
                                                    className="flex gap-2"
                                                >
                                                    <Input
                                                        name="issue_id"
                                                        placeholder="Issue id"
                                                        required
                                                    />
                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                    >
                                                        Link issue
                                                    </Button>
                                                </Form>
                                            </>
                                        ) : (
                                            <p className="text-muted-foreground text-[13px]">
                                                Submit a failed or blocked
                                                result first, then link or
                                                create an issue.
                                            </p>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}

                        {draft && can.execute && (
                            <Form
                                {...ExecutionController.destroy.form(draft.id)}
                                options={{ preserveScroll: true }}
                                className="mt-2"
                            >
                                <Button size="sm" variant="ghost">
                                    Discard draft
                                </Button>
                            </Form>
                        )}

                        <div className="bg-card mt-4 rounded-xl border p-[18px] shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                            <h3 className="mb-3 text-[15px] font-bold">
                                History
                            </h3>
                            <div className="space-y-4">
                                {history.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">
                                        No completed runs on this build yet.
                                    </p>
                                ) : (
                                    history.map((run) => (
                                        <HistoryRow
                                            key={run.id}
                                            run={run}
                                            can={can}
                                            issueTracker={issueTracker}
                                        />
                                    ))
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

function MiniVerdict({
    tone,
    pressed,
    onClick,
}: {
    tone: 'pass' | 'fail';
    pressed: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            title={tone === 'pass' ? 'Pass' : 'Fail'}
            onClick={onClick}
            className={cn(
                'grid size-[30px] place-items-center rounded-lg border-[1.5px]',
                pressed && tone === 'pass' && 'border-success bg-success-bg',
                pressed && tone === 'fail' && 'border-destructive bg-destructive-bg',
                !pressed && 'border-border bg-card',
            )}
        >
            <MockIcon
                name={tone === 'pass' ? 'check' : 'x'}
                className={cn(
                    'size-4',
                    tone === 'pass' ? 'text-success' : 'text-destructive',
                )}
            />
        </button>
    );
}

function HistoryRow({
    run,
    can,
    issueTracker,
}: {
    run: ExecutionRecord;
    can: { execute: boolean; delete: boolean; editNotes: boolean };
    issueTracker: { enabled: boolean; name: string | null };
}) {
    return (
        <div className="space-y-2 rounded-xl border p-3">
            <div className="flex flex-wrap items-center gap-2">
                <StatusPill status={run.status} />
                <span className="text-muted-foreground text-sm">
                    {run.tester ?? 'Unknown'} · v{run.version}
                    {run.executed_at ? ` · ${run.executed_at}` : ''}
                </span>
            </div>
            {run.notes && <p className="text-sm">{run.notes}</p>}
            {run.issues.length > 0 && (
                <ul className="space-y-1 text-sm">
                    {run.issues.map((issue) => (
                        <li key={issue.id}>
                            {issue.issue_url ? (
                                <a
                                    href={issue.issue_url}
                                    className="text-primary underline-offset-4 hover:underline"
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    {issue.issue_id}
                                </a>
                            ) : (
                                <span>{issue.issue_id}</span>
                            )}
                            {issue.issue_status && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    · {issue.issue_status}
                                </span>
                            )}
                            {issue.issue_summary && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    — {issue.issue_summary}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {can.editNotes && (
                <Form
                    {...ExecutionController.updateNotes.form(run.id)}
                    options={{ preserveScroll: true }}
                    className="space-y-2"
                >
                    <textarea
                        name="notes"
                        defaultValue={run.notes ?? ''}
                        rows={2}
                        className="border-border-strong bg-card w-full rounded-lg border px-3 py-2 text-sm"
                    />
                    <Button size="sm" variant="secondary">
                        Save notes
                    </Button>
                </Form>
            )}

            {can.execute &&
                issueTracker.enabled &&
                (run.status === 'failed' || run.status === 'blocked') && (
                    <Form
                        {...ExecutionIssueFromFailureController.store.form(
                            run.id,
                        )}
                        options={{ preserveScroll: true }}
                    >
                        <Button size="sm" variant="secondary">
                            Create {issueTracker.name ?? 'tracker'} issue
                        </Button>
                    </Form>
                )}

            {can.execute && (
                <Form
                    {...ExecutionIssueController.store.form(run.id)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="flex gap-2"
                >
                    <Input name="issue_id" placeholder="Issue id" required />
                    <Button size="sm" variant="secondary">
                        Link issue
                    </Button>
                </Form>
            )}

            {run.issues.map((issue) => (
                <Form
                    key={issue.id}
                    {...ExecutionIssueController.destroy.form(issue.id)}
                    options={{ preserveScroll: true }}
                >
                    <Button size="sm" variant="ghost">
                        Remove {issue.issue_id}
                    </Button>
                </Form>
            ))}

            {can.delete && (
                <Form
                    {...ExecutionController.destroy.form(run.id)}
                    options={{ preserveScroll: true }}
                >
                    <Button size="sm" variant="destructive">
                        Delete run
                    </Button>
                </Form>
            )}
        </div>
    );
}

function useElapsed(initialMinutes: string | null): {
    label: string;
    minutes: string;
} {
    const startedAt = useRef(
        Date.now() - (Number(initialMinutes) || 0) * 60_000,
    );
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(timer);
    }, []);

    const seconds = Math.max(0, Math.floor((now - startedAt.current) / 1000));
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    return {
        label: `${minutes}m ${String(rest).padStart(2, '0')}s elapsed`,
        minutes: (seconds / 60).toFixed(2),
    };
}
