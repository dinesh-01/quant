import { Form, Head, setLayoutProps } from '@inertiajs/react';
import CodeTrackerController from '@/actions/App/Http/Controllers/CodeTrackers/CodeTrackerController';
import CodeTrackerConnectionController from '@/actions/App/Http/Controllers/CodeTrackers/CodeTrackerConnectionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show as codeTrackerShow } from '@/routes/code-trackers';
import { index as projectIndex } from '@/routes/projects';

type TrackerForm = {
    name: string;
    type: string;
    base_url: string;
    project_key: string | null;
    view_url_template: string | null;
    is_enabled: boolean;
};

type CodeTrackerShowProps = {
    project: { id: number; name: string };
    tracker: TrackerForm | null;
    types: { value: string; label: string }[];
    can: { manage: boolean };
};

const selectClasses =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

export default function CodeTrackerShow({
    project,
    tracker,
    types,
    can,
}: CodeTrackerShowProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Code tracker', href: codeTrackerShow(project.id) },
        ],
    });

    return (
        <>
            <Head title={`${project.name} code tracker`} />

            <div className="max-w-2xl space-y-6 p-4">
                <Heading
                    title="Code tracker"
                    description={`Where ${project.name}'s automation scripts live. One tracker per project.`}
                />

                {!can.manage && tracker === null ? (
                    <p className="text-muted-foreground text-sm">
                        No code tracker is configured.
                    </p>
                ) : (
                    <Form
                        {...CodeTrackerController.update.form(project.id)}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={tracker?.name ?? ''}
                                        required
                                        maxLength={100}
                                        disabled={!can.manage}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="type">Type</Label>
                                    <select
                                        id="type"
                                        name="type"
                                        className={selectClasses}
                                        defaultValue={
                                            tracker?.type ?? 'generic'
                                        }
                                        disabled={!can.manage}
                                    >
                                        {types.map((type) => (
                                            <option
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.type} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="base_url">Base URL</Label>
                                    <Input
                                        id="base_url"
                                        name="base_url"
                                        type="url"
                                        defaultValue={tracker?.base_url ?? ''}
                                        required
                                        maxLength={255}
                                        placeholder="https://api.github.com"
                                        disabled={!can.manage}
                                    />
                                    <InputError message={errors.base_url} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="project_key">
                                        Project key
                                    </Label>
                                    <Input
                                        id="project_key"
                                        name="project_key"
                                        defaultValue={
                                            tracker?.project_key ?? ''
                                        }
                                        maxLength={100}
                                        placeholder="owner/repository"
                                        disabled={!can.manage}
                                    />
                                    <InputError message={errors.project_key} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="view_url_template">
                                        View URL template
                                    </Label>
                                    <Input
                                        id="view_url_template"
                                        name="view_url_template"
                                        defaultValue={
                                            tracker?.view_url_template ?? ''
                                        }
                                        maxLength={255}
                                        placeholder="{base_url}/{repository}/blob/{branch}/{path}"
                                        disabled={!can.manage}
                                    />
                                    <p className="text-muted-foreground text-xs">
                                        Placeholders: {'{base_url}'},{' '}
                                        {'{project_key}'}, {'{repository}'},{' '}
                                        {'{path}'}, {'{branch}'}, {'{commit}'}.
                                    </p>
                                    <InputError
                                        message={errors.view_url_template}
                                    />
                                </div>

                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="is_enabled"
                                        name="is_enabled"
                                        defaultChecked={
                                            tracker?.is_enabled ?? true
                                        }
                                        disabled={!can.manage}
                                    />
                                    <div className="grid gap-1">
                                        <Label htmlFor="is_enabled">
                                            Enabled
                                        </Label>
                                        <p className="text-muted-foreground text-xs">
                                            Script links still store a path when
                                            this is off; browse URLs are hidden.
                                        </p>
                                    </div>
                                </div>

                                {can.manage && (
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        Save tracker
                                    </Button>
                                )}
                            </>
                        )}
                    </Form>
                )}

                {can.manage && tracker !== null && (
                    <Form
                        {...CodeTrackerConnectionController.store.form(
                            project.id,
                        )}
                        className="space-y-4 border-t pt-6"
                    >
                        {({ processing }) => (
                            <>
                                <p className="text-muted-foreground text-sm">
                                    Probe the saved host. Save first if you
                                    changed the URL.
                                </p>
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    disabled={processing}
                                >
                                    Test connection
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}
