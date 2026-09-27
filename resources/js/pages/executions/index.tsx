import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import BulkExecutionController from '@/actions/App/Http/Controllers/Executions/BulkExecutionController';
import { StatusPill } from '@/components/chrome/stat-card';
import { RunCaseList } from '@/components/executions/run-case-list';
import { RunFilters } from '@/components/executions/run-filters';
import { RunWorkspaceHead } from '@/components/executions/run-workspace-head';
import { useRunFilters } from '@/components/executions/use-run-filters';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { index as executeIndex, show as executeShow } from '@/routes/executions';
import { index as selectorIndex } from '@/routes/plan-selector';
import type {
    ExecutionBuild,
    ExecutionListItem,
    ExecutionPlan,
    RunCounts,
} from '@/types/execution';
import { executionStatusLabels } from '@/types/execution';

type ExecutionIndexProps = {
    project: { id: number; name: string };
    plan: ExecutionPlan;
    builds: ExecutionBuild[];
    selectedBuildId: number | null;
    can: { execute: boolean };
    items: ExecutionListItem[];
    counts: RunCounts;
};

const selectClasses =
    'border-border-strong bg-card h-9 rounded-lg border px-3 text-sm';

export default function ExecutionIndex({
    project,
    plan,
    builds,
    selectedBuildId,
    can,
    items,
    counts = { passed: 0, failed: 0, blocked: 0, not_run: 0 },
}: ExecutionIndexProps) {
    const selectedBuild = builds.find((build) => build.id === selectedBuildId);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Execution', href: selectorIndex(project.id) },
            {
                title: `${plan.external_id} ${plan.name}`,
                href: executeIndex(plan.id),
            },
            { title: 'Run', href: executeIndex(plan.id) },
        ],
    });

    const rail = useRunFilters(items);
    const remaining = rail.visible.filter(
        (item) => item.latest_status === null,
    ).length;
    const platform = rail.visible[0]?.platform ?? items[0]?.platform ?? null;
    const nextCase =
        rail.visible.find((item) => item.latest_status === null) ??
        rail.visible[0] ??
        items.find((item) => item.latest_status === null) ??
        items[0];

    return (
        <>
            <Head title={`Execute ${plan.name}`} />

            <div className="flex flex-1 flex-col p-6">
                <RunWorkspaceHead
                    title={`${plan.name}${selectedBuild ? ` — ${selectedBuild.name}` : ''}`}
                    total={items.length}
                    assignedToViewer={items.some(
                        (item) => item.assigned_to_viewer,
                    )}
                    platform={platform}
                    counts={counts}
                    extra={
                        !plan.is_open ? (
                            <>
                                {' '}
                                · <StatusPill status="archived" label="Plan closed" />
                            </>
                        ) : null
                    }
                    projectId={project.id}
                    buildName={selectedBuild?.name}
                />

                {builds.length > 1 && (
                    <div className="mb-4 flex flex-wrap items-center gap-2.5">
                        {builds.map((build) => (
                            <Link
                                key={build.id}
                                href={executeIndex.url(plan.id, {
                                    query: { build: build.id },
                                })}
                                className={
                                    build.id === selectedBuildId
                                        ? 'border-primary bg-primary-50 text-primary-700 inline-flex items-center rounded-lg border px-3 py-1.5 text-[13px] font-semibold'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted inline-flex items-center rounded-lg border px-3 py-1.5 text-[13px] font-medium'
                                }
                            >
                                {build.name}
                                {!build.is_open && ' (closed)'}
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

                {builds.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        This plan has no builds yet. Add a build before
                        recording results.
                    </p>
                ) : selectedBuildId !== null && items.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No cases to run on this build.
                    </p>
                ) : (
                    <div className="grid grid-cols-1 gap-[18px] md:grid-cols-[300px_1fr]">
                        <RunCaseList
                            planId={plan.id}
                            buildId={selectedBuildId}
                            items={rail.visible}
                            remaining={remaining}
                        />

                        <div className="space-y-4">
                            <div className="bg-card rounded-xl border p-[18px] shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                                <p className="text-[15px] font-bold">
                                    Pick a case to run
                                </p>
                                <p className="text-muted-foreground mt-1 text-[13px]">
                                    Open a case from the rail to record step
                                    verdicts, attach evidence, and submit the
                                    result.
                                </p>
                                {nextCase && selectedBuildId !== null && (
                                    <Button className="mt-4" asChild>
                                        <Link
                                            href={executeShow.url(
                                                [plan.id, nextCase.id],
                                                {
                                                    query: {
                                                        build: selectedBuildId,
                                                    },
                                                },
                                            )}
                                        >
                                            {nextCase.latest_status === null
                                                ? 'Continue run'
                                                : 'Open first case'}
                                        </Link>
                                    </Button>
                                )}
                            </div>

                            {can.execute && selectedBuildId !== null && (
                                <Form
                                    {...BulkExecutionController.store.form(
                                        plan.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                    className="bg-card space-y-4 rounded-xl border p-[18px] shadow-[0_1px_2px_rgba(16,24,40,.06)]"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="build_id"
                                                value={selectedBuildId}
                                            />
                                            <p className="text-[13px] font-bold">
                                                Complete selected
                                            </p>
                                            <ul className="divide-border max-h-64 divide-y overflow-y-auto rounded-lg border">
                                                {rail.visible.map((item) => (
                                                    <li
                                                        key={item.id}
                                                        className="flex items-center gap-3 px-3 py-2"
                                                    >
                                                        <Checkbox
                                                            name="item_ids[]"
                                                            value={String(
                                                                item.id,
                                                            )}
                                                        />
                                                        <span className="min-w-0 text-sm">
                                                            <span className="text-text-subtle block font-mono text-[11px]">
                                                                {
                                                                    item.full_external_id
                                                                }
                                                            </span>
                                                            <span className="font-semibold">
                                                                {item.name}
                                                            </span>
                                                        </span>
                                                        {item.latest_status && (
                                                            <span className="ml-auto">
                                                                <StatusPill
                                                                    status={
                                                                        item.latest_status
                                                                    }
                                                                    label={
                                                                        executionStatusLabels[
                                                                            item
                                                                                .latest_status
                                                                        ]
                                                                    }
                                                                />
                                                            </span>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                            <div className="flex flex-wrap items-end gap-3">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="bulk-status">
                                                        Complete selected as
                                                    </Label>
                                                    <select
                                                        id="bulk-status"
                                                        name="status"
                                                        defaultValue="passed"
                                                        className={selectClasses}
                                                    >
                                                        <option value="passed">
                                                            Passed
                                                        </option>
                                                        <option value="failed">
                                                            Failed
                                                        </option>
                                                        <option value="blocked">
                                                            Blocked
                                                        </option>
                                                    </select>
                                                </div>
                                                <Button disabled={processing}>
                                                    Complete selected
                                                </Button>
                                            </div>
                                            <InputError
                                                message={errors.item_ids}
                                            />
                                            <InputError
                                                message={errors.status}
                                            />
                                        </>
                                    )}
                                </Form>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
