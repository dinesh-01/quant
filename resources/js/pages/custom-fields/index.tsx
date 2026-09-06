import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/custom-fields';
import type { CustomFieldSummary } from '@/types/custom-field';

type CustomFieldIndexProps = {
    fields: CustomFieldSummary[];
    can: { manage: boolean };
};

/**
 * The application-wide catalogue of definitions.
 *
 * Not under a project, because a definition is not owned by one: editing it
 * reaches every project that has it enabled, which is why the counts are shown
 * here — they are the blast radius of a change.
 */
export default function CustomFieldIndex({
    fields,
    can,
}: CustomFieldIndexProps) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Custom fields', href: index() }],
    });

    return (
        <>
            <Head title="Custom fields" />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Custom fields"
                        description="Extra fields projects can record against test cases, suites and plans."
                    />

                    {can.manage && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                New field
                            </Link>
                        </Button>
                    )}
                </div>

                {fields.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-10 text-center">
                        <p className="text-muted-foreground text-sm">
                            No custom fields yet. Define one here, then enable
                            it in the projects that should record it.
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {fields.map((field) => (
                            <li
                                key={field.id}
                                className="flex items-start justify-between gap-4 p-4"
                            >
                                <div className="min-w-0 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="font-medium">
                                            {field.label}
                                        </h2>

                                        <Badge variant="secondary">
                                            {field.entity_label}
                                        </Badge>

                                        <Badge variant="outline">
                                            {field.type_label}
                                        </Badge>
                                    </div>

                                    <p className="text-muted-foreground text-sm">
                                        <code>{field.name}</code> —{' '}
                                        {field.projects_count === 0
                                            ? 'not enabled in any project'
                                            : `enabled in ${field.projects_count} ${
                                                  field.projects_count === 1
                                                      ? 'project'
                                                      : 'projects'
                                              }`}
                                        {field.answers_count > 0 &&
                                            `, ${field.answers_count} filled in`}
                                    </p>
                                </div>

                                {can.manage && (
                                    <Button asChild size="sm" variant="ghost">
                                        <Link href={edit(field.id)}>
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
