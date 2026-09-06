import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Pencil, Plus, Tag } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/keywords';
import { index as projectIndex } from '@/routes/projects';
import { show as showSpecification } from '@/routes/specification';
import type { KeywordProject, KeywordSummary } from '@/types/keyword';

type KeywordIndexProps = {
    project: KeywordProject;
    keywords: KeywordSummary[];
    can: { manage: boolean };
};

/**
 * The project's keyword vocabulary.
 *
 * Curating the list and tagging cases with it are separate rights, so this
 * screen is read-only for someone who may only assign — the pickers on the
 * specification screen are where they work.
 */
export default function KeywordIndex({
    project,
    keywords,
    can,
}: KeywordIndexProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Keywords', href: index(project.id) },
        ],
    });

    return (
        <>
            <Head title={`${project.name} keywords`} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Keywords"
                        description={`The tags ${project.name} sorts its test cases by.`}
                    />

                    {can.manage && (
                        <Button asChild>
                            <Link href={create(project.id)}>
                                <Plus className="size-4" />
                                New keyword
                            </Link>
                        </Button>
                    )}
                </div>

                {keywords.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-10 text-center">
                        <p className="text-muted-foreground text-sm">
                            No keywords yet. They are free-form tags —{' '}
                            <span className="font-medium">smoke</span>,{' '}
                            <span className="font-medium">regression</span>,{' '}
                            <span className="font-medium">needs-data</span> —
                            that you can then filter the specification by.
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {keywords.map((keyword) => (
                            <li
                                key={keyword.id}
                                className="flex items-start justify-between gap-4 p-4"
                            >
                                <div className="min-w-0 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="flex items-center gap-1.5 font-medium">
                                            <Tag className="text-muted-foreground size-3.5" />
                                            {keyword.name}
                                        </h2>

                                        {keyword.test_cases_count === 0 ? (
                                            <Badge variant="outline">
                                                Unused
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary" asChild>
                                                <Link
                                                    href={`${showSpecification(project.id).url}?keywords[]=${keyword.id}`}
                                                >
                                                    {keyword.test_cases_count}{' '}
                                                    {keyword.test_cases_count ===
                                                    1
                                                        ? 'test case'
                                                        : 'test cases'}
                                                </Link>
                                            </Badge>
                                        )}
                                    </div>

                                    <p className="text-muted-foreground text-sm">
                                        {keyword.notes ?? 'No notes.'}
                                    </p>
                                </div>

                                {can.manage && (
                                    <Button asChild size="sm" variant="ghost">
                                        <Link href={edit(keyword.id)}>
                                            <Pencil className="size-4" />
                                            Edit
                                        </Link>
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
