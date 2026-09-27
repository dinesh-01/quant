import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { PageHead } from '@/components/chrome/page-head';
import { StatCard } from '@/components/chrome/stat-card';
import { Button } from '@/components/ui/button';
import { index as coverageIndex } from '@/routes/code-coverage';
import { index as projectSettings } from '@/routes/project-settings';

type Props = {
    project: { id: number; name: string };
    cases: number;
    links: {
        id: number;
        repository: string;
        path: string;
        branch: string | null;
        case: string;
        external_id: string;
        suite: string | null;
    }[];
    tracker: { name: string; type: string; is_enabled: boolean } | null;
};

export default function CodeCoverageIndex({
    project,
    cases,
    links,
    tracker,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Code Coverage', href: coverageIndex(project.id) },
        ],
    });

    const pct = cases === 0 ? 0 : Math.round((links.length / cases) * 100);

    return (
        <>
            <Head title={`${project.name} coverage`} />
            <div className="flex-1 space-y-5 p-6">
                <PageHead
                    title="Code Coverage"
                    description="Automation scripts linked to cases. Percentages are link coverage, not runtime coverage."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={projectSettings(project.id)}>
                                Integrations
                            </Link>
                        </Button>
                    }
                />
                <div className="grid gap-4 md:grid-cols-4">
                    <StatCard label="Cases" value={cases} />
                    <StatCard label="Linked scripts" value={links.length} />
                    <StatCard label="Link coverage" value={`${pct}%`} />
                    <StatCard
                        label="Tracker"
                        value={tracker?.is_enabled ? tracker.name : 'Off'}
                        hint={tracker?.type}
                    />
                </div>
                <div className="bg-card overflow-hidden rounded-xl border shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                    <table className="w-full text-[13.5px]">
                        <thead className="bg-muted text-muted-foreground text-left text-xs font-semibold tracking-[0.04em] uppercase">
                            <tr>
                                <th className="px-4 py-2 font-medium">
                                    Module
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    Script
                                </th>
                                <th className="px-4 py-2 font-medium">Case</th>
                            </tr>
                        </thead>
                        <tbody>
                            {links.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={3}
                                        className="text-muted-foreground px-4 py-10 text-center"
                                    >
                                        No automation scripts are linked yet.
                                    </td>
                                </tr>
                            ) : (
                                links.map((link) => (
                                    <tr key={link.id} className="border-t">
                                        <td className="px-4 py-3">
                                            {link.suite ?? link.repository}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs">
                                            {link.repository}/{link.path}
                                        </td>
                                        <td className="px-4 py-3">
                                            {link.case}
                                            <div className="text-muted-foreground text-xs">
                                                {link.external_id}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
