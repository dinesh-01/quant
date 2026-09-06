import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import BulkExecutionController from '@/actions/App/Http/Controllers/Executions/BulkExecutionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { index as executeIndex, show as executeShow } from '@/routes/executions';
import { index as selectorIndex } from '@/routes/plan-selector';
import type {
    ExecutionBuild,
    ExecutionListItem,
} from '@/types/execution';
import { executionStatusLabels } from '@/types/execution';

type ExecutionIndexProps = {
    project: { id: number; name: string };
    plan: { id: number; name: string; is_open: boolean };
    builds: ExecutionBuild[];
    selectedBuildId: number | null;
    can: { execute: boolean };
    items: ExecutionListItem[];
};

const selectClasses =
    'border-input bg-background h-9 rounded-md border px-3 text-sm';

export default function ExecutionIndex({
    project,
    plan,
    builds,
    selectedBuildId,
    can,
    items,
}: ExecutionIndexProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Execute', href: selectorIndex(project.id) },
            { title: plan.name, href: executeIndex(plan.id) },
        ],
    });

    return (
        <>
            <Head title={`Execute ${plan.name}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title={plan.name}
                    description={`Run cases in ${project.name}.`}
                />

                {builds.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        This plan has no builds yet. Add a build before
                        recording results.
                    </p>
                ) : (
                    <div className="flex flex-wrap gap-2">
                        {builds.map((build) => (
                            <Button
                                key={build.id}
                                asChild
                                size="sm"
                                variant={
                                    build.id === selectedBuildId
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                <Link
                                    href={executeIndex.url(plan.id, {
                                        query: { build: build.id },
                                    })}
                                >
                                    {build.name}
                                    {!build.is_open && ' (closed)'}
                                </Link>
                            </Button>
                        ))}
                    </div>
                )}

                {!plan.is_open && (
                    <Badge variant="secondary">Plan closed</Badge>
                )}

                {selectedBuildId !== null && items.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No cases to run on this build.
                    </p>
                ) : can.execute && selectedBuildId !== null ? (
                    <Form
                        {...BulkExecutionController.store.form(plan.id)}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="build_id"
                                    value={selectedBuildId}
                                />

                                <ul className="divide-y rounded-lg border">
                                    {items.map((item) => (
                                        <li
                                            key={item.id}
                                            className="flex items-center justify-between gap-4 p-4"
                                        >
                                            <label className="flex min-w-0 items-start gap-3">
                                                <Checkbox
                                                    name="item_ids[]"
                                                    value={String(item.id)}
                                                    className="mt-1"
                                                />
                                                <span className="min-w-0">
                                                    <span className="block font-medium">
                                                        {item.full_external_id}{' '}
                                                        {item.name}
                                                    </span>
                                                    <span className="text-muted-foreground text-sm">
                                                        v{item.version}
                                                        {item.platform
                                                            ? ` · ${item.platform}`
                                                            : ''}
                                                    </span>
                                                </span>
                                            </label>

                                            <div className="flex items-center gap-2">
                                                {item.latest_status && (
                                                    <Badge variant="secondary">
                                                        {executionStatusLabels[
                                                            item.latest_status
                                                        ] ?? item.latest_status}
                                                    </Badge>
                                                )}
                                                <Button asChild size="sm">
                                                    <Link
                                                        href={executeShow.url(
                                                            [plan.id, item.id],
                                                            {
                                                                query: {
                                                                    build: selectedBuildId,
                                                                },
                                                            },
                                                        )}
                                                    >
                                                        Run
                                                    </Link>
                                                </Button>
                                            </div>
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

                                <InputError message={errors.item_ids} />
                                <InputError message={errors.status} />
                            </>
                        )}
                    </Form>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {items.map((item) => (
                            <li
                                key={item.id}
                                className="flex items-center justify-between gap-4 p-4"
                            >
                                <div className="min-w-0">
                                    <p className="font-medium">
                                        {item.full_external_id} {item.name}
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        v{item.version}
                                        {item.platform
                                            ? ` · ${item.platform}`
                                            : ''}
                                    </p>
                                </div>

                                <div className="flex items-center gap-2">
                                    {item.latest_status && (
                                        <Badge variant="secondary">
                                            {executionStatusLabels[
                                                item.latest_status
                                            ] ?? item.latest_status}
                                        </Badge>
                                    )}
                                    <Button asChild size="sm">
                                        <Link
                                            href={executeShow.url(
                                                [plan.id, item.id],
                                                {
                                                    query: {
                                                        build: selectedBuildId ?? undefined,
                                                    },
                                                },
                                            )}
                                        >
                                            Run
                                        </Link>
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
