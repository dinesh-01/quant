import { Link } from '@inertiajs/react';
import { MockIcon } from '@/components/chrome/mock-icon';
import { PriorityMark, Tag } from '@/components/chrome/stat-card';
import { cn } from '@/lib/utils';
import { show as caseShow } from '@/routes/specification/cases';
import { importanceLabels } from '@/types/test-specification';
import type {
    SpecificationProject,
    TreeCase,
} from '@/types/test-specification';

export default function CaseTable({
    project,
    cases,
    selectedId,
}: {
    project: SpecificationProject;
    cases: TreeCase[];
    selectedId?: number | null;
}) {
    const rows = cases;

    if (rows.length === 0) {
        return (
            <p className="text-muted-foreground px-4 py-8 text-center text-sm">
                No cases in this suite.
            </p>
        );
    }

    return (
        <table className="w-full border-collapse text-[13.5px]">
            <thead className="bg-muted text-muted-foreground text-left text-xs font-semibold tracking-[0.04em] uppercase">
                <tr>
                    <th className="w-[26px] px-4 py-[11px]">
                        <span className="border-border-strong block size-4 rounded-[4px] border-[1.5px]" />
                    </th>
                    <th className="px-4 py-[11px]">Case</th>
                    <th className="px-4 py-[11px]">Priority</th>
                    <th className="px-4 py-[11px]">Keywords</th>
                    <th className="px-4 py-[11px]">Ver</th>
                </tr>
            </thead>
            <tbody>
                {rows.map((testCase) => {
                    const selected = selectedId === testCase.id;

                    return (
                        <tr
                            key={testCase.id}
                            className={cn(
                                'border-border border-t',
                                selected
                                    ? 'bg-primary-50'
                                    : 'hover:bg-muted/80',
                            )}
                        >
                            <td className="px-4 py-[13px]">
                                <span
                                    className={cn(
                                        'block size-4 rounded-[4px] border-[1.5px]',
                                        selected
                                            ? 'border-primary bg-primary'
                                            : 'border-border-strong',
                                    )}
                                    aria-hidden
                                />
                            </td>
                            <td className="px-4 py-[13px] align-middle">
                                <Link
                                    href={caseShow([project.id, testCase.id])}
                                    preserveState
                                    preserveScroll
                                    className="flex flex-col gap-0.5"
                                >
                                    <span className="font-semibold">
                                        {testCase.name}
                                    </span>
                                    <span className="text-muted-foreground flex items-center font-mono text-xs">
                                        {testCase.full_external_id}
                                        {testCase.is_open === false && (
                                            <span className="text-neutral bg-neutral-bg border-neutral-border ml-1.5 inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold">
                                                <MockIcon
                                                    name="lock"
                                                    className="size-[11px]"
                                                />
                                                Frozen
                                            </span>
                                        )}
                                    </span>
                                </Link>
                            </td>
                            <td className="px-4 py-[13px]">
                                {testCase.importance ? (
                                    <PriorityMark
                                        priority={
                                            importanceLabels[
                                                testCase.importance
                                            ] ?? testCase.importance
                                        }
                                    />
                                ) : (
                                    '—'
                                )}
                            </td>
                            <td className="px-4 py-[13px]">
                                {testCase.keyword ? (
                                    <Tag>{testCase.keyword}</Tag>
                                ) : (
                                    <span className="text-muted-foreground">
                                        —
                                    </span>
                                )}
                            </td>
                            <td className="text-muted-foreground px-4 py-[13px] font-mono text-xs">
                                {testCase.version
                                    ? `v${testCase.version}`
                                    : '—'}
                            </td>
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
}
