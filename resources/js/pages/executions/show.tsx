import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import AttachmentController from '@/actions/App/Http/Controllers/Attachments/AttachmentController';
import ExecutionController from '@/actions/App/Http/Controllers/Executions/ExecutionController';
import ExecutionIssueController from '@/actions/App/Http/Controllers/Executions/ExecutionIssueController';
import ExecutionIssueFromFailureController from '@/actions/App/Http/Controllers/Executions/ExecutionIssueFromFailureController';
import AttachmentList from '@/components/attachments/attachment-list';
import CustomFieldInputs from '@/components/custom-fields/custom-field-inputs';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import RichText from '@/components/rich-text/rich-text';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as executeIndex, show as executeShow } from '@/routes/executions';
import { index as selectorIndex } from '@/routes/plan-selector';
import type { AttachmentRules, AttachmentSummary } from '@/types/attachment';
import type { CustomFieldInput } from '@/types/custom-field';
import type { ExecutionRecord } from '@/types/execution';
import { executionStatusLabels } from '@/types/execution';

const selectClasses =
    'border-input bg-background h-9 rounded-md border px-3 text-sm';

type ExecutionShowProps = {
    project: { id: number; name: string };
    plan: { id: number; name: string; is_open: boolean };
    build: { id: number; name: string; is_open: boolean };
    item: {
        id: number;
        full_external_id: string;
        name: string;
        version: number;
        summary: string | null;
        preconditions: string | null;
        platform: string | null;
        steps: {
            id: number;
            sort_order: number;
            actions: string | null;
            expected_results: string | null;
        }[];
    };
    draft: ExecutionRecord | null;
    draftId: number | null;
    history: ExecutionRecord[];
    statuses: { value: string; label: string }[];
    customFields: CustomFieldInput[];
    attachments: AttachmentSummary[];
    attachmentRules: AttachmentRules;
    issueTracker: { enabled: boolean; name: string | null };
    can: { execute: boolean; delete: boolean; editNotes: boolean };
};

export default function ExecutionShow({
    project,
    plan,
    build,
    item,
    draft,
    draftId,
    history,
    statuses,
    customFields,
    attachments,
    attachmentRules,
    issueTracker,
    can,
}: ExecutionShowProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Execute', href: selectorIndex(project.id) },
            {
                title: plan.name,
                href: executeIndex.url(plan.id, {
                    query: { build: build.id },
                }),
            },
            {
                title: item.full_external_id,
                href: executeShow.url([plan.id, item.id], {
                    query: { build: build.id },
                }),
            },
        ],
    });

    const stepResult = (stepId: number) =>
        draft?.steps.find((step) => step.test_case_step_id === stepId);

    return (
        <>
            <Head title={`Run ${item.full_external_id}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title={`${item.full_external_id} ${item.name}`}
                    description={`${plan.name} · ${build.name} · v${item.version}${item.platform ? ` · ${item.platform}` : ''}`}
                />

                <Card>
                    <CardContent className="space-y-3 pt-6">
                        <RichText html={item.summary} empty="No summary." />
                        {item.preconditions && (
                            <div>
                                <p className="text-muted-foreground text-sm">
                                    Preconditions
                                </p>
                                <RichText html={item.preconditions} />
                            </div>
                        )}
                    </CardContent>
                </Card>

                {can.execute ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {draft ? 'Resume draft' : 'Record result'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...ExecutionController.store.form(item.id)}
                                options={{ preserveScroll: true }}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="build_id"
                                            value={build.id}
                                        />

                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="status">
                                                    Result
                                                </Label>
                                                <select
                                                    id="status"
                                                    name="status"
                                                    defaultValue={
                                                        draft?.status ??
                                                        'not_run'
                                                    }
                                                    className={selectClasses}
                                                >
                                                    {statuses.map((status) => (
                                                        <option
                                                            key={status.value}
                                                            value={status.value}
                                                        >
                                                            {executionStatusLabels[
                                                                status.value
                                                            ] ?? status.label}
                                                        </option>
                                                    ))}
                                                </select>
                                                <InputError
                                                    message={errors.status}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="duration">
                                                    Duration (minutes)
                                                </Label>
                                                <Input
                                                    id="duration"
                                                    name="duration"
                                                    type="number"
                                                    min={0}
                                                    step="0.01"
                                                    defaultValue={
                                                        draft?.duration ?? ''
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="notes">Notes</Label>
                                            <textarea
                                                id="notes"
                                                name="notes"
                                                defaultValue={draft?.notes ?? ''}
                                                rows={3}
                                                className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                            />
                                        </div>

                                        <CustomFieldInputs
                                            fields={customFields}
                                            errors={errors}
                                        />

                                        {item.steps.map((step, index) => {
                                            const result = stepResult(step.id);

                                            return (
                                                <div
                                                    key={step.id}
                                                    className="space-y-2 rounded-md border p-3"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name={`steps[${index}][test_case_step_id]`}
                                                        value={step.id}
                                                    />
                                                    <p className="text-sm font-medium">
                                                        Step {step.sort_order}
                                                    </p>
                                                    <RichText
                                                        html={step.actions}
                                                        empty="No action."
                                                    />
                                                    <p className="text-muted-foreground text-xs">
                                                        Expected
                                                    </p>
                                                    <RichText
                                                        html={
                                                            step.expected_results
                                                        }
                                                        empty="None."
                                                    />
                                                    <div className="grid gap-2 sm:grid-cols-2">
                                                        <select
                                                            name={`steps[${index}][status]`}
                                                            defaultValue={
                                                                result?.status ??
                                                                'not_run'
                                                            }
                                                            className={
                                                                selectClasses
                                                            }
                                                        >
                                                            {statuses.map(
                                                                (status) => (
                                                                    <option
                                                                        key={
                                                                            status.value
                                                                        }
                                                                        value={
                                                                            status.value
                                                                        }
                                                                    >
                                                                        {executionStatusLabels[
                                                                            status.value
                                                                        ] ??
                                                                            status.label}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                        <Input
                                                            name={`steps[${index}][notes]`}
                                                            defaultValue={
                                                                result?.notes ??
                                                                ''
                                                            }
                                                            placeholder="Step notes"
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        })}

                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                variant="secondary"
                                                disabled={processing}
                                            >
                                                Save draft
                                            </Button>
                                            <Button
                                                name="complete"
                                                value="1"
                                                disabled={processing}
                                            >
                                                Complete run
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        This plan or build is closed, or you may only inspect
                        results.
                    </p>
                )}

                {draftId !== null && can.execute && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Run attachments</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <AttachmentList
                                attachments={attachments}
                                rules={attachmentRules}
                                upload={AttachmentController.storeForExecution.form(
                                    draftId,
                                )}
                                canManage
                                describedAs="this run"
                            />
                        </CardContent>
                    </Card>
                )}

                {draft && can.execute && (
                    <Form
                        {...ExecutionController.destroy.form(draft.id)}
                        options={{ preserveScroll: true }}
                    >
                        <Button size="sm" variant="ghost">
                            Discard draft
                        </Button>
                    </Form>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>History</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {history.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No completed runs on this build yet.
                            </p>
                        ) : (
                            history.map((run) => (
                                <div
                                    key={run.id}
                                    className="space-y-2 rounded-md border p-3"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge>
                                            {executionStatusLabels[run.status] ??
                                                run.status}
                                        </Badge>
                                        <span className="text-muted-foreground text-sm">
                                            {run.tester ?? 'Unknown'} · v
                                            {run.version}
                                            {run.executed_at
                                                ? ` · ${run.executed_at}`
                                                : ''}
                                        </span>
                                    </div>
                                    {run.notes && (
                                        <p className="text-sm">{run.notes}</p>
                                    )}
                                    {run.issues.length > 0 && (
                                        <ul className="space-y-1 text-sm">
                                            {run.issues.map((issue) => (
                                                <li key={issue.id}>
                                                    {issue.issue_url ? (
                                                        <a
                                                            href={
                                                                issue.issue_url
                                                            }
                                                            className="text-primary underline-offset-4 hover:underline"
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            {issue.issue_id}
                                                        </a>
                                                    ) : (
                                                        <span>
                                                            {issue.issue_id}
                                                        </span>
                                                    )}
                                                    {issue.issue_status && (
                                                        <span className="text-muted-foreground">
                                                            {' '}
                                                            ·{' '}
                                                            {issue.issue_status}
                                                        </span>
                                                    )}
                                                    {issue.issue_summary && (
                                                        <span className="text-muted-foreground">
                                                            {' '}
                                                            —{' '}
                                                            {issue.issue_summary}
                                                        </span>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    )}

                                    {can.editNotes && (
                                        <Form
                                            {...ExecutionController.updateNotes.form(
                                                run.id,
                                            )}
                                            options={{ preserveScroll: true }}
                                            className="space-y-2"
                                        >
                                            <textarea
                                                name="notes"
                                                defaultValue={run.notes ?? ''}
                                                rows={2}
                                                className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                            />
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                            >
                                                Save notes
                                            </Button>
                                        </Form>
                                    )}

                                    {can.execute &&
                                        issueTracker.enabled &&
                                        (run.status === 'failed' ||
                                            run.status === 'blocked') && (
                                            <Form
                                                {...ExecutionIssueFromFailureController.store.form(
                                                    run.id,
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

                                    {can.execute && (
                                        <Form
                                            {...ExecutionIssueController.store.form(
                                                run.id,
                                            )}
                                            options={{ preserveScroll: true }}
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
                                    )}

                                    {run.issues.map((issue) => (
                                        <Form
                                            key={issue.id}
                                            {...ExecutionIssueController.destroy.form(
                                                issue.id,
                                            )}
                                            options={{ preserveScroll: true }}
                                        >
                                            <Button size="sm" variant="ghost">
                                                Remove {issue.issue_id}
                                            </Button>
                                        </Form>
                                    ))}

                                    {can.delete && (
                                        <Form
                                            {...ExecutionController.destroy.form(
                                                run.id,
                                            )}
                                            options={{ preserveScroll: true }}
                                        >
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                            >
                                                Delete run
                                            </Button>
                                        </Form>
                                    )}
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
