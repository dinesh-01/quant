import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import ProjectCustomFieldController from '@/actions/App/Http/Controllers/CustomFields/ProjectCustomFieldController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { index as projectCustomFieldIndex } from '@/routes/projects/custom-fields';
import { index as projectIndex } from '@/routes/projects';
import type {
    AvailableCustomField,
    ProjectCustomField,
} from '@/types/custom-field';

type ProjectCustomFieldsProps = {
    project: { id: number; name: string };
    assigned: ProjectCustomField[];
    available: AvailableCustomField[];
};

/**
 * Which of the catalogue's fields this project records.
 *
 * Everything is submitted as one form: the rows left in it are the project's
 * fields afterwards, so removing a row is how a field is dropped. That makes
 * the destructive act explicit — removing takes this project's answers with it,
 * which is why each row says how many there are, and why switching a field off
 * is offered as the reversible alternative.
 */
export default function ProjectCustomFields({
    project,
    assigned,
    available,
}: ProjectCustomFieldsProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            {
                title: 'Custom fields',
                href: projectCustomFieldIndex(project.id),
            },
        ],
    });

    const [rows, setRows] = useState<ProjectCustomField[]>(assigned);
    const [adding, setAdding] = useState('');

    const remaining = available.filter(
        (candidate) => !rows.some((row) => row.id === candidate.id),
    );

    const add = (id: string) => {
        const field = available.find(
            (candidate) => candidate.id === Number(id),
        );

        if (!field) {
            return;
        }

        setRows([
            ...rows,
            {
                ...field,
                is_active: true,
                sort_order: rows.length,
                required_on_design: false,
                required_on_execution: false,
                answers_count: 0,
            },
        ]);

        setAdding('');
    };

    return (
        <>
            <Head title={`${project.name} custom fields`} />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading
                    title="Custom fields"
                    description={`The extra fields ${project.name} records. Definitions are shared, so what a field is stays the same everywhere — this decides which of them appear here and which are mandatory.`}
                />

                <Form
                    {...ProjectCustomFieldController.update.form(project.id)}
                    className="space-y-6"
                >
                    {({ processing }) => (
                        <>
                            {rows.length === 0 ? (
                                <div className="rounded-lg border border-dashed p-10 text-center">
                                    <p className="text-muted-foreground text-sm">
                                        This project records no custom fields.
                                    </p>
                                </div>
                            ) : (
                                <ul className="divide-y rounded-lg border">
                                    {rows.map((row, index) => (
                                        <li
                                            key={row.id}
                                            className="space-y-3 p-4"
                                        >
                                            <input
                                                type="hidden"
                                                name={`fields[${index}][id]`}
                                                value={row.id}
                                            />
                                            <input
                                                type="hidden"
                                                name={`fields[${index}][sort_order]`}
                                                value={index}
                                            />

                                            <div className="flex items-start justify-between gap-4">
                                                <div className="min-w-0 space-y-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <h2 className="font-medium">
                                                            {row.label}
                                                        </h2>

                                                        <Badge variant="secondary">
                                                            {row.entity_label}
                                                        </Badge>

                                                        <Badge variant="outline">
                                                            {row.type_label}
                                                        </Badge>
                                                    </div>

                                                    <p className="text-muted-foreground text-sm">
                                                        {row.answers_count === 0
                                                            ? 'Nothing filled in yet.'
                                                            : `${row.answers_count} ${row.answers_count === 1 ? 'answer' : 'answers'} in this project, which removing the field would delete.`}
                                                    </p>
                                                </div>

                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setRows(
                                                            rows.filter(
                                                                (candidate) =>
                                                                    candidate.id !==
                                                                    row.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    Remove
                                                </Button>
                                            </div>

                                            <div className="flex flex-wrap gap-6">
                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        id={`active_${row.id}`}
                                                        name={`fields[${index}][is_active]`}
                                                        value="1"
                                                        defaultChecked={
                                                            row.is_active
                                                        }
                                                    />

                                                    <Label
                                                        htmlFor={`active_${row.id}`}
                                                        className="font-normal"
                                                    >
                                                        Shown on forms
                                                    </Label>
                                                </div>

                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        id={`design_${row.id}`}
                                                        name={`fields[${index}][required_on_design]`}
                                                        value="1"
                                                        defaultChecked={
                                                            row.required_on_design
                                                        }
                                                    />

                                                    <Label
                                                        htmlFor={`design_${row.id}`}
                                                        className="font-normal"
                                                    >
                                                        Required when authoring
                                                    </Label>
                                                </div>

                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        id={`execution_${row.id}`}
                                                        name={`fields[${index}][required_on_execution]`}
                                                        value="1"
                                                        defaultChecked={
                                                            row.required_on_execution
                                                        }
                                                    />

                                                    <Label
                                                        htmlFor={`execution_${row.id}`}
                                                        className="font-normal"
                                                    >
                                                        Required when executing
                                                    </Label>
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            {remaining.length > 0 && (
                                <div className="grid gap-2">
                                    <Label htmlFor="add">Add a field</Label>

                                    <select
                                        id="add"
                                        value={adding}
                                        onChange={(event) =>
                                            add(event.target.value)
                                        }
                                        className="border-input focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                    >
                                        <option value="">
                                            Choose from the catalogue…
                                        </option>

                                        {remaining.map((candidate) => (
                                            <option
                                                key={candidate.id}
                                                value={candidate.id}
                                            >
                                                {candidate.label} (
                                                {candidate.entity_label},{' '}
                                                {candidate.type_label})
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            <Button type="submit" disabled={processing}>
                                Save fields
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
