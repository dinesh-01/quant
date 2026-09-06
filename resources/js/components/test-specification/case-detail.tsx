import { Form, Link } from '@inertiajs/react';
import { Copy, FolderInput, Lock, LockOpen, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AttachmentController from '@/actions/App/Http/Controllers/Attachments/AttachmentController';
import TestCaseController from '@/actions/App/Http/Controllers/TestSpecification/TestCaseController';
import TestCaseStepController from '@/actions/App/Http/Controllers/TestSpecification/TestCaseStepController';
import TestCaseVersionController from '@/actions/App/Http/Controllers/TestSpecification/TestCaseVersionController';
import AttachmentList from '@/components/attachments/attachment-list';
import CustomFieldInputs from '@/components/custom-fields/custom-field-inputs';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text/lazy-rich-text-editor';
import RichText from '@/components/rich-text/rich-text';
import CaseCoverage from '@/components/test-specification/case-coverage';
import CaseScriptLinks from '@/components/test-specification/case-script-links';
import CaseKeywords from '@/components/test-specification/case-keywords';
import CasePlatforms from '@/components/test-specification/case-platforms';
import CaseRelations from '@/components/test-specification/case-relations';
import ReorderControls from '@/components/test-specification/reorder-controls';
import CopyTargetPicker from '@/components/test-specification/copy-target-picker';
import SuitePicker from '@/components/test-specification/suite-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { caseSiblings } from '@/lib/specification-tree';
import { cn } from '@/lib/utils';
import { show as caseShow } from '@/routes/specification/cases';
import type { AttachmentRules } from '@/types/attachment';
import type { KeywordOption } from '@/types/keyword';
import type { PlatformOption } from '@/types/platform';
import type {
    CaseDetail,
    SpecificationAbilities,
    SpecificationProject,
    TreeSuite,
} from '@/types/test-specification';
import {
    executionTypeLabels,
    importanceLabels,
    statusLabels,
} from '@/types/test-specification';

const textareaClasses =
    'border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none disabled:opacity-60';

/**
 * The detail pane for a selected test case.
 *
 * A frozen version is rendered read-only rather than hidden: the server refuses
 * the write anyway, and a reviewer still needs to read what was executed. The
 * way forward is either reopening it or opening a new version.
 */
export default function CaseDetailPane({
    project,
    tree,
    testCase,
    can,
    keywords,
    platforms,
    attachmentRules,
}: {
    project: SpecificationProject;
    tree: TreeSuite[];
    testCase: CaseDetail;
    can: SpecificationAbilities;
    keywords: KeywordOption[];
    platforms: PlatformOption[];
    attachmentRules: AttachmentRules;
}) {
    const version = testCase.version;
    const frozen = version !== null && !version.is_open;
    const editable = can.manage && !frozen;
    const images =
        version?.attachments.filter((attachment) => attachment.is_image) ?? [];

    /** Versions arrive oldest first, so the last is the newest. */
    const newest = testCase.versions.at(-1) ?? null;
    const showingNewest =
        version === null || version.version === newest?.version;

    const siblings = caseSiblings(tree, testCase.id);

    /**
     * Moving a case into the suite it already sits in would send it to the end
     * of that suite, so Move stays disabled until the target changes.
     */
    const here = String(testCase.test_suite_id);
    const [moveTo, setMoveTo] = useState(here);

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="text-muted-foreground text-sm">
                        {testCase.suite_name}
                    </p>

                    <h1 className="text-2xl font-semibold">
                        <span className="text-muted-foreground mr-2 font-mono text-base">
                            {testCase.full_external_id}
                        </span>
                        {testCase.name}
                    </h1>
                </div>

                {version !== null && (
                    <div className="flex items-center gap-2">
                        <Badge variant={frozen ? 'secondary' : 'outline'}>
                            Version {version.version}
                            {frozen ? ' · frozen' : ''}
                        </Badge>

                        {can.freeze && (
                            <Form
                                {...(frozen
                                    ? TestCaseVersionController.unfreeze.form(
                                          version.id,
                                      )
                                    : TestCaseVersionController.freeze.form(
                                          version.id,
                                      ))}
                                options={{ preserveScroll: true }}
                            >
                                <Button variant="outline" size="sm">
                                    {frozen ? (
                                        <LockOpen className="size-4" />
                                    ) : (
                                        <Lock className="size-4" />
                                    )}
                                    {frozen ? 'Reopen' : 'Freeze'}
                                </Button>
                            </Form>
                        )}

                        {can.manage && (
                            <Form
                                {...TestCaseVersionController.store.form(
                                    testCase.id,
                                )}
                                options={{ preserveScroll: true }}
                            >
                                <Button variant="outline" size="sm">
                                    <Plus className="size-4" />
                                    New version
                                </Button>
                            </Form>
                        )}
                    </div>
                )}
            </div>

            {/*
             * Above the version pane because keywords are on the case: they do
             * not change when the reader switches version.
             */}
            <CaseKeywords
                project={project}
                testCase={testCase}
                keywords={keywords}
                canAssign={can.assignKeywords}
            />

            <CaseRelations
                project={project}
                testCase={testCase}
                can={can}
            />

            {version === null ? (
                <p className="text-muted-foreground text-sm">
                    This test case has no versions.
                </p>
            ) : (
                <>
                    {testCase.versions.length > 1 && (
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-muted-foreground text-sm">
                                Versions
                            </span>

                            {testCase.versions.map((each) => (
                                <Link
                                    key={each.id}
                                    href={caseShow([project.id, testCase.id], {
                                        query: { version: each.version },
                                    })}
                                    preserveScroll
                                    className={cn(
                                        'rounded border px-2 py-0.5 text-sm',
                                        each.version === version.version
                                            ? 'bg-muted font-medium'
                                            : 'hover:bg-muted text-muted-foreground',
                                    )}
                                    aria-current={
                                        each.version === version.version
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    {each.version}
                                    {!each.is_open && (
                                        <Lock className="ml-1 inline size-3" />
                                    )}
                                </Link>
                            ))}
                        </div>
                    )}

                    {!showingNewest && newest !== null && (
                        <p className="bg-muted rounded-md px-3 py-2 text-sm">
                            You are reading version {version.version}.{' '}
                            <Link
                                href={caseShow([project.id, testCase.id])}
                                className="underline"
                            >
                                Version {newest.version} is the newest
                            </Link>
                            , and is the one a new version would be copied from.
                        </p>
                    )}

                    {frozen && (
                        <p className="bg-muted text-muted-foreground rounded-md px-3 py-2 text-sm">
                            This version is frozen because executions may refer
                            to it. Reopen it or create a new version to make
                            changes.
                        </p>
                    )}

                    <CasePlatforms
                        project={project}
                        version={version}
                        platforms={platforms}
                        canAssign={editable}
                    />

                    <CaseCoverage
                        project={project}
                        version={version}
                        can={can}
                    />

                    <CaseScriptLinks version={version} can={can} />

                    {can.manage && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Name</CardTitle>
                            </CardHeader>

                            <CardContent>
                                <Form
                                    {...TestCaseController.update.form(
                                        testCase.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                    className="flex items-start gap-2"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="flex-1 space-y-2">
                                                <Input
                                                    name="name"
                                                    defaultValue={testCase.name}
                                                    aria-label="Test case name"
                                                    required
                                                />

                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>

                                            <Button disabled={processing}>
                                                Rename
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Version {version.version}</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <Form
                                {...TestCaseVersionController.update.form(
                                    version.id,
                                )}
                                options={{ preserveScroll: true }}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <InputError message={errors.version} />

                                        <div className="grid gap-4 sm:grid-cols-3">
                                            <div className="grid gap-2">
                                                <Label htmlFor="status">
                                                    Status
                                                </Label>

                                                <select
                                                    id="status"
                                                    name="status"
                                                    defaultValue={
                                                        version.status
                                                    }
                                                    disabled={!editable}
                                                    className={textareaClasses}
                                                >
                                                    {Object.entries(
                                                        statusLabels,
                                                    ).map(([value, label]) => (
                                                        <option
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="importance">
                                                    Importance
                                                </Label>

                                                <select
                                                    id="importance"
                                                    name="importance"
                                                    defaultValue={
                                                        version.importance
                                                    }
                                                    disabled={!editable}
                                                    className={textareaClasses}
                                                >
                                                    {Object.entries(
                                                        importanceLabels,
                                                    ).map(([value, label]) => (
                                                        <option
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="execution_type">
                                                    Execution
                                                </Label>

                                                <select
                                                    id="execution_type"
                                                    name="execution_type"
                                                    defaultValue={
                                                        version.execution_type
                                                    }
                                                    disabled={!editable}
                                                    className={textareaClasses}
                                                >
                                                    {Object.entries(
                                                        executionTypeLabels,
                                                    ).map(([value, label]) => (
                                                        <option
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>

                                        {/*
                                         * A frozen version reads rather than
                                         * edits. The editor has no disabled
                                         * state worth trusting — a
                                         * contenteditable region that looks
                                         * greyed out still takes keystrokes —
                                         * so the read-only path renders the
                                         * markup instead of an inert editor.
                                         */}
                                        <div className="grid gap-2">
                                            <Label>Summary</Label>

                                            {editable ? (
                                                <RichTextEditor
                                                    name="summary"
                                                    defaultValue={
                                                        version.summary
                                                    }
                                                    ariaLabel="Summary"
                                                    placeholder="What this test case is for."
                                                    images={images}
                                                    ghostCases={
                                                        testCase.relatable
                                                    }
                                                    ghostKind="summary"
                                                />
                                            ) : (
                                                <RichText
                                                    html={version.summary}
                                                    empty="No summary."
                                                />
                                            )}

                                            <InputError
                                                message={errors.summary}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label>Preconditions</Label>

                                            {editable ? (
                                                <RichTextEditor
                                                    name="preconditions"
                                                    defaultValue={
                                                        version.preconditions
                                                    }
                                                    ariaLabel="Preconditions"
                                                    placeholder="What has to be true before a tester starts."
                                                    images={images}
                                                    ghostCases={
                                                        testCase.relatable
                                                    }
                                                    ghostKind="preconditions"
                                                />
                                            ) : (
                                                <RichText
                                                    html={version.preconditions}
                                                    empty="No preconditions."
                                                />
                                            )}

                                            <InputError
                                                message={errors.preconditions}
                                            />
                                        </div>

                                        <div className="grid gap-2 sm:max-w-48">
                                            <Label htmlFor="estimated_duration">
                                                Estimated minutes
                                            </Label>

                                            <Input
                                                id="estimated_duration"
                                                name="estimated_duration"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                defaultValue={
                                                    version.estimated_duration ??
                                                    ''
                                                }
                                                disabled={!editable}
                                            />

                                            <InputError
                                                message={
                                                    errors.estimated_duration
                                                }
                                            />
                                        </div>

                                        {/*
                                         * Inside this form, so an answer saves
                                         * with the version it belongs to — a
                                         * frozen one refuses both together
                                         * rather than taking the answers and
                                         * rejecting the rest.
                                         */}
                                        <CustomFieldInputs
                                            fields={version.custom_fields}
                                            errors={errors}
                                            readOnly={!editable}
                                        />

                                        {editable && (
                                            <Button disabled={processing}>
                                                Save version
                                            </Button>
                                        )}
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Steps</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            {version.steps.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    No steps yet.
                                </p>
                            )}

                            {version.steps.map((step) => (
                                <div key={step.id} className="space-y-2">
                                    {/*
                                     * Outside the edit form below: a form
                                     * cannot be nested in another, and each
                                     * reorder control is its own form.
                                     */}
                                    <div className="flex items-center justify-between">
                                        <Label>Step {step.sort_order}</Label>

                                        {editable && (
                                            <ReorderControls
                                                action={TestCaseStepController.reorder.form(
                                                    version.id,
                                                )}
                                                order={version.steps.map(
                                                    (each) => each.id,
                                                )}
                                                id={step.id}
                                                label={`step ${step.sort_order}`}
                                            />
                                        )}
                                    </div>

                                    <Form
                                        {...TestCaseStepController.update.form(
                                            step.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                        className="space-y-2"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="flex items-center justify-end">
                                                    <select
                                                        name="execution_type"
                                                        defaultValue={
                                                            step.execution_type
                                                        }
                                                        disabled={!editable}
                                                        aria-label={`Execution type for step ${step.sort_order}`}
                                                        className="border-input bg-background rounded-md border px-2 py-1 text-xs"
                                                    >
                                                        {Object.entries(
                                                            executionTypeLabels,
                                                        ).map(
                                                            ([
                                                                value,
                                                                label,
                                                            ]) => (
                                                                <option
                                                                    key={value}
                                                                    value={
                                                                        value
                                                                    }
                                                                >
                                                                    {label}
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </div>

                                                <div className="grid gap-2 sm:grid-cols-2">
                                                    {editable ? (
                                                        <>
                                                            <RichTextEditor
                                                                name="actions"
                                                                defaultValue={
                                                                    step.actions
                                                                }
                                                                placeholder="Actions"
                                                                ariaLabel={`Actions for step ${step.sort_order}`}
                                                                images={images}
                                                                ghostCases={
                                                                    testCase.relatable
                                                                }
                                                                ghostKind="step"
                                                            />

                                                            <RichTextEditor
                                                                name="expected_results"
                                                                defaultValue={
                                                                    step.expected_results
                                                                }
                                                                placeholder="Expected results"
                                                                ariaLabel={`Expected results for step ${step.sort_order}`}
                                                                images={images}
                                                                ghostCases={
                                                                    testCase.relatable
                                                                }
                                                                ghostKind="step"
                                                            />
                                                        </>
                                                    ) : (
                                                        <>
                                                            <RichText
                                                                html={
                                                                    step.actions
                                                                }
                                                                empty="No actions."
                                                            />

                                                            <RichText
                                                                html={
                                                                    step.expected_results
                                                                }
                                                                empty="No expected results."
                                                            />
                                                        </>
                                                    )}
                                                </div>

                                                <InputError
                                                    message={errors.step}
                                                />

                                                {editable && (
                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                        disabled={processing}
                                                    >
                                                        Save step
                                                    </Button>
                                                )}
                                            </>
                                        )}
                                    </Form>

                                    {editable && (
                                        <Form
                                            {...TestCaseStepController.destroy.form(
                                                step.id,
                                            )}
                                            options={{ preserveScroll: true }}
                                        >
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="text-destructive"
                                            >
                                                <Trash2 className="size-4" />
                                                Remove step
                                            </Button>
                                        </Form>
                                    )}

                                    <Separator />
                                </div>
                            ))}

                            {editable && (
                                <Form
                                    {...TestCaseStepController.store.form(
                                        version.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    className="space-y-2"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2 sm:grid-cols-2">
                                                <RichTextEditor
                                                    name="actions"
                                                    defaultValue={null}
                                                    placeholder="Actions"
                                                    ariaLabel="Actions for the new step"
                                                    images={images}
                                                    ghostCases={
                                                        testCase.relatable
                                                    }
                                                    ghostKind="step"
                                                />

                                                <RichTextEditor
                                                    name="expected_results"
                                                    defaultValue={null}
                                                    placeholder="Expected results"
                                                    ariaLabel="Expected results for the new step"
                                                    images={images}
                                                    ghostCases={
                                                        testCase.relatable
                                                    }
                                                    ghostKind="step"
                                                />
                                            </div>

                                            <input
                                                type="hidden"
                                                name="execution_type"
                                                value={version.execution_type}
                                            />

                                            <InputError message={errors.step} />

                                            <Button
                                                variant="secondary"
                                                disabled={processing}
                                            >
                                                <Plus className="size-4" />
                                                Add step
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}
                        </CardContent>
                    </Card>

                    {/*
                     * Files hang off the version, not the case: a screenshot is
                     * evidence about one revision of the steps, so a frozen
                     * version keeps the evidence of what was executed. Uploads
                     * follow `manage`, since a frozen version refuses writes.
                     */}
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Attachments for version {version.version}
                            </CardTitle>
                        </CardHeader>

                        <CardContent>
                            <AttachmentList
                                attachments={version.attachments}
                                rules={attachmentRules}
                                upload={AttachmentController.storeForVersion.form(
                                    version.id,
                                )}
                                canManage={editable}
                                describedAs="this version"
                            />
                        </CardContent>
                    </Card>

                    {can.manage && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Organisation</CardTitle>
                            </CardHeader>

                            <CardContent className="space-y-6">
                                {siblings.length > 1 && (
                                    <div className="space-y-2">
                                        <Label>
                                            Position in {testCase.suite_name}
                                        </Label>

                                        <ReorderControls
                                            action={TestCaseController.reorder.form(
                                                testCase.test_suite_id,
                                            )}
                                            order={siblings}
                                            id={testCase.id}
                                            label="test case"
                                        />
                                    </div>
                                )}

                                <Form
                                    {...TestCaseController.move.form(
                                        testCase.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                    className="space-y-2"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <Label htmlFor="move-suite">
                                                Move into
                                            </Label>

                                            <div className="flex items-start gap-2">
                                                <SuitePicker
                                                    id="move-suite"
                                                    name="test_suite_id"
                                                    suites={tree}
                                                    value={moveTo}
                                                    onValueChange={setMoveTo}
                                                />

                                                <Button
                                                    variant="secondary"
                                                    disabled={
                                                        processing ||
                                                        moveTo === here
                                                    }
                                                >
                                                    <FolderInput className="size-4" />
                                                    Move
                                                </Button>
                                            </div>

                                            <InputError
                                                message={errors.test_suite_id}
                                            />

                                            <p className="text-muted-foreground text-xs">
                                                Keeps{' '}
                                                {testCase.full_external_id}, so
                                                links to this test case stay
                                                valid.
                                            </p>
                                        </>
                                    )}
                                </Form>

                                <Form
                                    {...TestCaseController.copy.form(
                                        testCase.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                    className="space-y-2"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <Label htmlFor="copy-suite">
                                                Copy into
                                            </Label>

                                            <div className="flex items-start gap-2">
                                                <CopyTargetPicker
                                                    id="copy-suite"
                                                    name="test_suite_id"
                                                    project={project}
                                                    tree={tree}
                                                    defaultSuiteId={
                                                        testCase.test_suite_id
                                                    }
                                                />

                                                <Button
                                                    variant="secondary"
                                                    disabled={processing}
                                                >
                                                    <Copy className="size-4" />
                                                    Copy
                                                </Button>
                                            </div>

                                            <InputError
                                                message={errors.test_suite_id}
                                            />

                                            <p className="text-muted-foreground text-xs">
                                                Copies every version and gets a
                                                new number.
                                            </p>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    )}

                    <p className="text-muted-foreground text-sm">
                        Written by {version.author ?? 'an unknown user'}
                        {version.updater !== null &&
                            `, last changed by ${version.updater}`}
                        .
                    </p>

                    {can.manage && (
                        <>
                            <Separator />

                            <Form
                                {...TestCaseController.destroy.form(
                                    testCase.id,
                                )}
                                onBefore={() =>
                                    confirm(
                                        `Delete ${testCase.full_external_id} and all of its versions? This cannot be undone.`,
                                    )
                                }
                            >
                                {({ processing }) => (
                                    <Button
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        <Trash2 className="size-4" />
                                        Delete test case
                                    </Button>
                                )}
                            </Form>
                        </>
                    )}
                </>
            )}
        </div>
    );
}
