import { Form } from '@inertiajs/react';
import { Copy, FolderInput, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AttachmentController from '@/actions/App/Http/Controllers/Attachments/AttachmentController';
import TestCaseController from '@/actions/App/Http/Controllers/TestSpecification/TestCaseController';
import TestSuiteController from '@/actions/App/Http/Controllers/TestSpecification/TestSuiteController';
import AttachmentList from '@/components/attachments/attachment-list';
import CustomFieldInputs from '@/components/custom-fields/custom-field-inputs';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text/lazy-rich-text-editor';
import RichText from '@/components/rich-text/rich-text';
import ReorderControls from '@/components/test-specification/reorder-controls';
import SuiteKeywords from '@/components/test-specification/suite-keywords';
import CopyTargetPicker from '@/components/test-specification/copy-target-picker';
import SuitePicker from '@/components/test-specification/suite-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { subtreeIds, suiteSiblings } from '@/lib/specification-tree';
import type { AttachmentRules } from '@/types/attachment';
import type { KeywordOption } from '@/types/keyword';
import type {
    SpecificationAbilities,
    SpecificationProject,
    SuiteDetail,
    TreeSuite,
} from '@/types/test-specification';

/**
 * The detail pane for a selected suite: rename it, add a child suite or a case
 * beneath it, move or copy it elsewhere, or delete it.
 */
export default function SuiteDetailPane({
    project,
    tree,
    suite,
    can,
    keywords,
    attachmentRules,
}: {
    project: SpecificationProject;
    tree: TreeSuite[];
    suite: SuiteDetail;
    can: SpecificationAbilities;
    keywords: KeywordOption[];
    attachmentRules: AttachmentRules;
}) {
    const siblings = suiteSiblings(tree, suite.id);

    /**
     * A suite can go anywhere but into itself or its own descendants, which
     * would detach the subtree from the tree.
     */
    const ownSubtree = subtreeIds(tree, suite.id);

    /**
     * Tracked so Move stays disabled until the target really changes. Moving a
     * suite under the parent it already has is not a no-op — it would send it
     * to the end of its siblings — so it must not be one click away by accident.
     */
    const here = String(suite.parent_id ?? '');
    const [moveTo, setMoveTo] = useState(here);

    return (
        <div className="space-y-6">
            <div>
                <p className="text-muted-foreground text-sm">
                    {suite.path.map((step) => step.name).join(' / ')}
                </p>

                <h1 className="text-2xl font-semibold">{suite.name}</h1>
            </div>

            {!can.manage ? (
                <Card>
                    <CardContent className="space-y-4 pt-6">
                        <RichText
                            html={suite.description}
                            empty="No description."
                        />
                    </CardContent>
                </Card>
            ) : (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle>Test suite</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <Form
                                {...TestSuiteController.update.form(suite.id)}
                                options={{ preserveScroll: true }}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="suite-name">
                                                Name
                                            </Label>

                                            <Input
                                                id="suite-name"
                                                name="name"
                                                defaultValue={suite.name}
                                                required
                                            />

                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2">
                                            {/*
                                             * No `htmlFor`: the editor is a
                                             * contenteditable region rather
                                             * than a labellable control, so it
                                             * names itself with `aria-label`.
                                             */}
                                            <Label>Description</Label>

                                            <RichTextEditor
                                                name="description"
                                                defaultValue={suite.description}
                                                ariaLabel="Test suite description"
                                                placeholder="What this suite covers."
                                                images={suite.attachments.filter(
                                                    (attachment) =>
                                                        attachment.is_image,
                                                )}
                                            />

                                            <InputError
                                                message={errors.description}
                                            />
                                        </div>

                                        <CustomFieldInputs
                                            fields={suite.custom_fields}
                                            errors={errors}
                                        />

                                        <Button disabled={processing}>
                                            Save
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Add a child test suite</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <Form
                                {...TestSuiteController.store.form(project.id)}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="flex items-start gap-2"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="parent_id"
                                            value={suite.id}
                                        />

                                        <div className="flex-1 space-y-2">
                                            <Input
                                                name="name"
                                                placeholder="Test suite name"
                                                aria-label="New test suite name"
                                                required
                                            />

                                            <InputError message={errors.name} />
                                        </div>

                                        <Button
                                            variant="secondary"
                                            disabled={processing}
                                        >
                                            Add suite
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Add a test case</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <Form
                                {...TestCaseController.store.form(suite.id)}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="flex items-start gap-2"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="flex-1 space-y-2">
                                            <Input
                                                name="name"
                                                placeholder="Test case name"
                                                aria-label="New test case name"
                                                required
                                            />

                                            <InputError message={errors.name} />
                                        </div>

                                        <Button disabled={processing}>
                                            Add case
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Organisation</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-6">
                            {siblings.order.length > 1 && (
                                <div className="space-y-2">
                                    <Label>Position among its siblings</Label>

                                    <ReorderControls
                                        action={TestSuiteController.reorder.form(
                                            project.id,
                                        )}
                                        order={siblings.order}
                                        id={suite.id}
                                        label="test suite"
                                        extra={{
                                            parent_id: siblings.parentId,
                                        }}
                                    />
                                </div>
                            )}

                            <Form
                                {...TestSuiteController.move.form(suite.id)}
                                options={{ preserveScroll: true }}
                                className="space-y-2"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Label htmlFor="move-parent">
                                            Move into
                                        </Label>

                                        <div className="flex items-start gap-2">
                                            <SuitePicker
                                                id="move-parent"
                                                name="parent_id"
                                                suites={tree}
                                                exclude={ownSubtree}
                                                rootLabel={`${project.name} (top level)`}
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
                                            message={errors.parent_id}
                                        />
                                    </>
                                )}
                            </Form>

                            <Form
                                {...TestSuiteController.copy.form(suite.id)}
                                options={{ preserveScroll: true }}
                                className="space-y-2"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Label htmlFor="copy-parent">
                                            Copy into
                                        </Label>

                                        <div className="flex items-start gap-2">
                                            <CopyTargetPicker
                                                id="copy-parent"
                                                name="parent_id"
                                                project={project}
                                                tree={tree}
                                                exclude={ownSubtree}
                                                rootLabel={`${project.name} (top level)`}
                                                defaultSuiteId={suite.parent_id}
                                                includeProjectId
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
                                            message={errors.parent_id}
                                        />

                                        <p className="text-muted-foreground text-xs">
                                            Copies this suite, everything nested
                                            under it, and every version of its
                                            test cases. Copied test cases get
                                            new numbers.
                                        </p>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </>
            )}

            {/*
             * Its own card, and outside every other form on this pane: the list
             * carries upload and delete forms of its own, and a form cannot be
             * nested in another.
             */}
            <Card>
                <CardHeader>
                    <CardTitle>Attachments</CardTitle>
                </CardHeader>

                <CardContent>
                    <AttachmentList
                        attachments={suite.attachments}
                        rules={attachmentRules}
                        upload={AttachmentController.storeForSuite.form(
                            suite.id,
                        )}
                        canManage={can.manage}
                        describedAs="this test suite"
                    />
                </CardContent>
            </Card>

            {/*
             * Outside the `manage` branch: tagging cases is its own right, so
             * someone who may assign keywords but not edit the specification
             * still gets the bulk tool.
             */}
            <SuiteKeywords
                project={project}
                suite={suite}
                keywords={keywords}
                canAssign={can.assignKeywords}
            />

            {can.manage && (
                <>
                    <Separator />

                    <Form
                        {...TestSuiteController.destroy.form(suite.id)}
                        options={{ preserveScroll: true }}
                        onBefore={() =>
                            confirm(
                                `Delete "${suite.name}" and everything inside it? This cannot be undone.`,
                            )
                        }
                    >
                        {({ processing }) => (
                            <Button variant="destructive" disabled={processing}>
                                <Trash2 className="size-4" />
                                Delete test suite
                            </Button>
                        )}
                    </Form>
                </>
            )}
        </div>
    );
}
