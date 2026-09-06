import { Form, Link } from '@inertiajs/react';
import { Bell, BellOff, FolderInput, Lock, LockOpen, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import RequirementController from '@/actions/App/Http/Controllers/Requirements/RequirementController';
import RequirementMonitorController from '@/actions/App/Http/Controllers/Requirements/RequirementMonitorController';
import RequirementVersionController from '@/actions/App/Http/Controllers/Requirements/RequirementVersionController';
import { destroy as destroyCoverage, store as linkFromRequirement } from '@/routes/requirement-coverages';
import SpecPicker from '@/components/requirements/spec-picker';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text/lazy-rich-text-editor';
import RichText from '@/components/rich-text/rich-text';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show as caseShow } from '@/routes/specification/cases';
import { show as requirementShow } from '@/routes/requirements/items';
import type {
    EnumOption,
    RequirementDetail,
    RequirementsAbilities,
    RequirementsProject,
    TreeSpec,
} from '@/types/requirements';
import {
    requirementStatusLabels,
    requirementTypeLabels,
} from '@/types/requirements';

const selectClasses =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

export default function RequirementDetailPane({
    project,
    tree,
    requirement,
    can,
    statuses,
    types,
}: {
    project: RequirementsProject;
    tree: TreeSpec[];
    requirement: RequirementDetail;
    can: RequirementsAbilities;
    statuses: EnumOption[];
    types: EnumOption[];
}) {
    const version = requirement.version;
    const editable = can.manage && version !== null && version.is_open;
    const [moveTo, setMoveTo] = useState(String(requirement.requirement_spec_id));

    return (
        <div className="space-y-6">
            <div>
                <p className="text-muted-foreground text-sm">
                    {requirement.spec_name}
                </p>
                <h1 className="text-2xl font-semibold">{requirement.name}</h1>
                <p className="text-muted-foreground text-sm">
                    {requirement.doc_id}
                </p>
            </div>

            {can.monitor && (
                <Form
                    {...(requirement.watching_id
                        ? RequirementMonitorController.destroy.form(
                              requirement.watching_id,
                          )
                        : RequirementMonitorController.store.form(
                              requirement.id,
                          ))}
                    options={{ preserveScroll: true }}
                >
                    <Button size="sm" variant="outline">
                        {requirement.watching_id ? (
                            <>
                                <BellOff className="size-4" />
                                Stop watching
                            </>
                        ) : (
                            <>
                                <Bell className="size-4" />
                                Watch
                            </>
                        )}
                    </Button>
                </Form>
            )}

            {can.manage && (
                <Card>
                    <CardHeader>
                        <CardTitle>Requirement</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Form
                            {...RequirementController.update.form(
                                requirement.id,
                            )}
                            options={{ preserveScroll: true }}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="req-name">
                                                Name
                                            </Label>
                                            <Input
                                                id="req-name"
                                                name="name"
                                                defaultValue={requirement.name}
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="req-doc">
                                                Document id
                                            </Label>
                                            <Input
                                                id="req-doc"
                                                name="doc_id"
                                                defaultValue={requirement.doc_id}
                                                required
                                            />
                                            <InputError
                                                message={errors.doc_id}
                                            />
                                        </div>
                                    </div>
                                    <Button disabled={processing}>Save</Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            )}

            {version === null ? (
                <p className="text-muted-foreground text-sm">
                    This requirement has no versions.
                </p>
            ) : (
                <>
                    {requirement.versions.length > 1 && (
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-muted-foreground text-sm">
                                Versions
                            </span>
                            {requirement.versions.map((each) => (
                                <Link
                                    key={each.id}
                                    href={requirementShow(
                                        [project.id, requirement.id],
                                        { query: { version: each.version } },
                                    )}
                                >
                                    <Badge
                                        variant={
                                            each.version === version.version
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        v{each.version}
                                        {!each.is_open && ' (frozen)'}
                                    </Badge>
                                </Link>
                            ))}
                        </div>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Version {version.version}
                                {!version.is_open && (
                                    <Badge className="ml-2" variant="secondary">
                                        Frozen
                                    </Badge>
                                )}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {editable ? (
                                <Form
                                    {...RequirementVersionController.update.form(
                                        version.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label>Scope</Label>
                                                <RichTextEditor
                                                    name="scope"
                                                    defaultValue={
                                                        version.scope ?? ''
                                                    }
                                                    aria-label="Requirement scope"
                                                />
                                            </div>
                                            <div className="grid gap-2 sm:grid-cols-3">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="req-status">
                                                        Status
                                                    </Label>
                                                    <select
                                                        id="req-status"
                                                        name="status"
                                                        defaultValue={
                                                            version.status
                                                        }
                                                        className={selectClasses}
                                                    >
                                                        {statuses.map(
                                                            (status) => (
                                                                <option
                                                                    key={
                                                                        status.value
                                                                    }
                                                                    value={
                                                                        status.value
                                                                    }
                                                                >
                                                                    {
                                                                        status.label
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="req-type">
                                                        Type
                                                    </Label>
                                                    <select
                                                        id="req-type"
                                                        name="type"
                                                        defaultValue={
                                                            version.type
                                                        }
                                                        className={selectClasses}
                                                    >
                                                        {types.map((type) => (
                                                            <option
                                                                key={type.value}
                                                                value={
                                                                    type.value
                                                                }
                                                            >
                                                                {type.label}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="req-expected">
                                                        Expected coverage
                                                    </Label>
                                                    <Input
                                                        id="req-expected"
                                                        name="expected_coverage"
                                                        type="number"
                                                        min={0}
                                                        defaultValue={
                                                            version.expected_coverage
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.expected_coverage
                                                        }
                                                    />
                                                </div>
                                            </div>
                                            <Button disabled={processing}>
                                                Save version
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            ) : (
                                <div className="space-y-3">
                                    <RichText
                                        html={version.scope}
                                        empty="No scope."
                                    />
                                    <p className="text-sm">
                                        {requirementStatusLabels[version.status] ??
                                            version.status}{' '}
                                        ·{' '}
                                        {requirementTypeLabels[version.type] ??
                                            version.type}{' '}
                                        · expected {version.expected_coverage}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Coverage</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {version.coverages.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No test case versions cover this wording.
                                </p>
                            ) : (
                                <ul className="space-y-2">
                                    {version.coverages.map((coverage) => (
                                        <li
                                            key={coverage.id}
                                            className="flex items-center justify-between gap-2 text-sm"
                                        >
                                            <Link
                                                href={caseShow([
                                                    project.id,
                                                    coverage.test_case_id,
                                                ], {
                                                    query: {
                                                        version: coverage.test_case_version,
                                                    },
                                                })}
                                                className="font-medium underline-offset-4 hover:underline"
                                            >
                                                {coverage.full_external_id}{' '}
                                                {coverage.name} (v
                                                {coverage.test_case_version})
                                            </Link>
                                            {can.coverage && (
                                                <Form
                                                    {...destroyCoverage.form(
                                                        coverage.id,
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                    >
                                                        Remove
                                                    </Button>
                                                </Form>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}

                            {can.coverage && version.coverable.length > 0 && (
                                <Form
                                    {...linkFromRequirement.form(version.id)}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    className="grid gap-3 sm:grid-cols-2"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="test_case_version_id">
                                                    Test case version
                                                </Label>
                                                <select
                                                    id="test_case_version_id"
                                                    name="test_case_version_id"
                                                    className={selectClasses}
                                                    required
                                                >
                                                    {version.coverable.map(
                                                        (other) => (
                                                            <option
                                                                key={other.id}
                                                                value={other.id}
                                                            >
                                                                {
                                                                    other.full_external_id
                                                                }{' '}
                                                                {other.name} (v
                                                                {other.version})
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                <InputError
                                                    message={
                                                        errors.test_case_version_id
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <Button
                                                    variant="secondary"
                                                    disabled={processing}
                                                >
                                                    Link coverage
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex flex-wrap gap-2">
                        {can.manage && (
                            <Form
                                {...RequirementVersionController.store.form(
                                    requirement.id,
                                )}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        <Plus className="size-4" />
                                        New version
                                    </Button>
                                )}
                            </Form>
                        )}
                        {can.manage && version.is_open && (
                            <Form
                                {...RequirementVersionController.freeze.form(
                                    version.id,
                                )}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        <Lock className="size-4" />
                                        Freeze
                                    </Button>
                                )}
                            </Form>
                        )}
                        {can.unfreeze && !version.is_open && (
                            <Form
                                {...RequirementVersionController.unfreeze.form(
                                    version.id,
                                )}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        <LockOpen className="size-4" />
                                        Reopen
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                </>
            )}

            {can.manage && (
                <Card>
                    <CardHeader>
                        <CardTitle>Organisation</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <Form
                            {...RequirementController.move.form(requirement.id)}
                            options={{ preserveScroll: true }}
                            className="space-y-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Label htmlFor="move-spec">Move into</Label>
                                    <div className="flex items-start gap-2">
                                        <SpecPicker
                                            id="move-spec"
                                            name="requirement_spec_id"
                                            specs={tree}
                                            value={moveTo}
                                            onValueChange={setMoveTo}
                                        />
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            disabled={
                                                processing ||
                                                moveTo ===
                                                    String(
                                                        requirement.requirement_spec_id,
                                                    )
                                            }
                                        >
                                            <FolderInput className="size-4" />
                                            Move
                                        </Button>
                                    </div>
                                    <InputError
                                        message={errors.requirement_spec_id}
                                    />
                                </>
                            )}
                        </Form>

                        <Form
                            {...RequirementController.destroy.form(
                                requirement.id,
                            )}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    <Trash2 className="size-4" />
                                    Delete requirement
                                </Button>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
