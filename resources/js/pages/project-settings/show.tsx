import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import EventLogController from '@/actions/App/Http/Controllers/Audit/EventLogController';
import TokenController from '@/actions/App/Http/Controllers/Settings/TokenController';
import TestProjectController from '@/actions/App/Http/Controllers/TestProjects/TestProjectController';
import { PageHead } from '@/components/chrome/page-head';
import InputError from '@/components/input-error';
import ProjectFormFields from '@/components/test-projects/project-form-fields';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show as codeTrackerShow } from '@/routes/code-trackers';
import { show as issueTrackerShow } from '@/routes/issue-trackers';
import { index as projectCustomFields } from '@/routes/projects/custom-fields';
import { index as projectSettings } from '@/routes/project-settings';
import type { AttachmentRules, AttachmentSummary } from '@/types/attachment';

type Tab =
    | 'general'
    | 'fields'
    | 'tokens'
    | 'integrations'
    | 'notifications'
    | 'housekeeping';

type Props = {
    project: {
        id: number;
        name: string;
        prefix: string;
        description: string | null;
        is_active: boolean;
        is_public: boolean;
        prefix_locked: boolean;
        test_cases_count: number;
        attachments: AttachmentSummary[];
    };
    attachmentRules: AttachmentRules;
    assignedFields: { id: number; name: string; label: string }[];
    tokens: {
        id: number;
        name: string;
        last_used_at: string | null;
        created_at: string | null;
    }[];
    plainTextToken: string | null;
    codeTracker: { name: string; type: string; is_enabled: boolean } | null;
    issueTracker: { name: string; type: string; is_enabled: boolean } | null;
    can: {
        manageProject: boolean;
        assignCustomFields: boolean;
        manageCodeTrackers: boolean;
        manageIssueTrackers: boolean;
        prune: boolean;
    };
};

export default function ProjectSettings({
    project,
    assignedFields,
    tokens,
    plainTextToken,
    codeTracker,
    issueTracker,
    can,
}: Props) {
    const [tab, setTab] = useState<Tab>('general');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Settings', href: projectSettings(project.id) },
        ],
    });

    const tabs: { id: Tab; label: string; count?: number }[] = [
        { id: 'general', label: 'General' },
        { id: 'fields', label: 'Custom fields', count: assignedFields.length },
        { id: 'tokens', label: 'API tokens', count: tokens.length },
        { id: 'integrations', label: 'Integrations' },
        { id: 'notifications', label: 'Notifications' },
        { id: 'housekeeping', label: 'Housekeeping' },
    ];

    return (
        <>
            <Head title={`${project.name} settings`} />
            <div className="flex-1 space-y-5 p-6">
                <PageHead
                    title="Settings"
                    description={`Configure the ${project.name} project — fields, tokens, integrations and housekeeping.`}
                />
                <div className="grid gap-4 lg:grid-cols-[224px_1fr]">
                    <nav className="bg-card h-fit rounded-xl border p-2 shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                        {tabs.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => setTab(item.id)}
                                className={
                                    tab === item.id
                                        ? 'bg-primary-50 text-primary-700 flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-[13.5px] font-semibold'
                                        : 'hover:bg-muted flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-[13.5px] font-medium'
                                }
                            >
                                <span>{item.label}</span>
                                {item.count !== undefined && (
                                    <span className="text-xs opacity-70">
                                        {item.count}
                                    </span>
                                )}
                            </button>
                        ))}
                    </nav>

                    <div className="bg-card rounded-xl border p-5 shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                        {tab === 'general' && (
                            <Form
                                {...TestProjectController.update.form(project.id)}
                                className="space-y-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <ProjectFormFields
                                            errors={errors}
                                            defaults={project}
                                            prefixLocked={project.prefix_locked}
                                        />
                                        {can.manageProject && (
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Save changes
                                            </Button>
                                        )}
                                    </>
                                )}
                            </Form>
                        )}

                        {tab === 'fields' && (
                            <div className="space-y-3">
                                <p className="text-muted-foreground text-sm">
                                    {assignedFields.length} fields assigned to
                                    this project.
                                </p>
                                <ul className="divide-y rounded-lg border">
                                    {assignedFields.map((field) => (
                                        <li
                                            key={field.id}
                                            className="px-3 py-2 text-sm"
                                        >
                                            {field.label}
                                        </li>
                                    ))}
                                </ul>
                                {can.assignCustomFields && (
                                    <Button asChild>
                                        <Link
                                            href={projectCustomFields(
                                                project.id,
                                            )}
                                        >
                                            Manage custom fields
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        )}

                        {tab === 'tokens' && (
                            <div className="space-y-4">
                                {plainTextToken && (
                                    <Input
                                        readOnly
                                        value={plainTextToken}
                                        className="font-mono"
                                    />
                                )}
                                <Form
                                    {...TokenController.store.form()}
                                    className="flex gap-2"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="flex-1">
                                                <Input
                                                    name="name"
                                                    placeholder="CI pipeline"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>
                                            <Button disabled={processing}>
                                                Create
                                            </Button>
                                        </>
                                    )}
                                </Form>
                                <ul className="divide-y rounded-lg border">
                                    {tokens.map((token) => (
                                        <li
                                            key={token.id}
                                            className="px-3 py-2 text-sm"
                                        >
                                            {token.name}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {tab === 'integrations' && (
                            <div className="space-y-4">
                                <div className="flex items-center justify-between rounded-lg border p-3">
                                    <div>
                                        <p className="font-medium">
                                            Code tracker
                                        </p>
                                        <p className="text-muted-foreground text-sm">
                                            {codeTracker?.name ??
                                                'Not configured'}
                                        </p>
                                    </div>
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={codeTrackerShow(project.id)}
                                        >
                                            Configure
                                        </Link>
                                    </Button>
                                </div>
                                <div className="flex items-center justify-between rounded-lg border p-3">
                                    <div>
                                        <p className="font-medium">
                                            Issue tracker
                                        </p>
                                        <p className="text-muted-foreground text-sm">
                                            {issueTracker?.name ??
                                                'Not configured'}
                                        </p>
                                    </div>
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={issueTrackerShow(project.id)}
                                        >
                                            Configure
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        )}

                        {tab === 'notifications' && (
                            <p className="text-muted-foreground text-sm">
                                Notification preferences are not stored yet.
                                These controls stay visual only.
                            </p>
                        )}

                        {tab === 'housekeeping' && can.prune && (
                            <Form
                                {...EventLogController.destroy.form()}
                                className="space-y-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Label htmlFor="keep_days">
                                            Keep events newer than (days)
                                        </Label>
                                        <Input
                                            id="keep_days"
                                            name="keep_days"
                                            type="number"
                                            min={30}
                                            defaultValue={90}
                                        />
                                        <InputError message={errors.keep_days} />
                                        <Button
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            Prune event log
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}

                        {tab === 'housekeeping' && !can.prune && (
                            <p className="text-muted-foreground text-sm">
                                Only accounts that can manage the event log can
                                prune it.
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
