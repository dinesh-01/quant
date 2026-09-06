import { Form, Head, setLayoutProps } from '@inertiajs/react';
import IssueTrackerController from '@/actions/App/Http/Controllers/IssueTrackers/IssueTrackerController';
import IssueTrackerConnectionController from '@/actions/App/Http/Controllers/IssueTrackers/IssueTrackerConnectionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show as issueTrackerShow } from '@/routes/issue-trackers';
import { index as projectIndex } from '@/routes/projects';

type TrackerForm = {
    name: string;
    type: string;
    base_url: string;
    project_key: string | null;
    issue_type: string | null;
    email: string | null;
    has_api_token: boolean;
    proxy: string | null;
    is_enabled: boolean;
};

type IssueTrackerShowProps = {
    project: { id: number; name: string };
    tracker: TrackerForm | null;
    types: { value: string; label: string }[];
    can: { manage: boolean };
};

const selectClasses =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

export default function IssueTrackerShow({
    project,
    tracker,
    types,
    can,
}: IssueTrackerShowProps) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Test projects', href: projectIndex() },
            { title: 'Issue tracker', href: issueTrackerShow(project.id) },
        ],
    });

    return (
        <>
            <Head title={`${project.name} issue tracker`} />

            <div className="max-w-2xl space-y-6 p-4">
                <Heading
                    title="Issue tracker"
                    description={`Where ${project.name}'s failed runs open tickets. One tracker per project. Jira Cloud only.`}
                />

                {!can.manage && tracker === null ? (
                    <p className="text-muted-foreground text-sm">
                        No issue tracker is configured.
                    </p>
                ) : (
                    <Form
                        {...IssueTrackerController.update.form(project.id)}
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
                                        defaultValue={tracker?.type ?? 'jira'}
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
                                    <Label htmlFor="base_url">Site URL</Label>
                                    <Input
                                        id="base_url"
                                        name="base_url"
                                        type="url"
                                        defaultValue={tracker?.base_url ?? ''}
                                        required
                                        maxLength={255}
                                        placeholder="https://acme.atlassian.net"
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
                                        required
                                        maxLength={32}
                                        placeholder="PAY"
                                        disabled={!can.manage}
                                    />
                                    <InputError message={errors.project_key} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="issue_type">
                                        Issue type
                                    </Label>
                                    <Input
                                        id="issue_type"
                                        name="issue_type"
                                        defaultValue={
                                            tracker?.issue_type ?? 'Bug'
                                        }
                                        required
                                        maxLength={64}
                                        disabled={!can.manage}
                                    />
                                    <InputError message={errors.issue_type} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        autoComplete="username"
                                        defaultValue={tracker?.email ?? ''}
                                        required
                                        maxLength={255}
                                        disabled={!can.manage}
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="api_token">API token</Label>
                                    <Input
                                        id="api_token"
                                        name="api_token"
                                        type="password"
                                        autoComplete="new-password"
                                        placeholder={
                                            tracker?.has_api_token
                                                ? 'Leave blank to keep the saved token'
                                                : ''
                                        }
                                        maxLength={255}
                                        disabled={!can.manage}
                                    />
                                    <InputError message={errors.api_token} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="proxy">
                                        HTTP proxy (optional)
                                    </Label>
                                    <Input
                                        id="proxy"
                                        name="proxy"
                                        type="url"
                                        defaultValue={tracker?.proxy ?? ''}
                                        maxLength={255}
                                        placeholder="https://proxy.example:8080"
                                        disabled={!can.manage}
                                    />
                                    <InputError message={errors.proxy} />
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
                                            Create-from-fail is hidden while
                                            this is off. Manual issue ids still
                                            store.
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
                        {...IssueTrackerConnectionController.store.form(
                            project.id,
                        )}
                        className="space-y-4 border-t pt-6"
                    >
                        {({ processing }) => (
                            <>
                                <p className="text-muted-foreground text-sm">
                                    Probe the saved host with these
                                    credentials. Save first if you changed
                                    them.
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
