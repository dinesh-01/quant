import { Form } from '@inertiajs/react';
import { FolderInput, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import RequirementController from '@/actions/App/Http/Controllers/Requirements/RequirementController';
import RequirementSpecController from '@/actions/App/Http/Controllers/Requirements/RequirementSpecController';
import SpecPicker from '@/components/requirements/spec-picker';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text/lazy-rich-text-editor';
import RichText from '@/components/rich-text/rich-text';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { subtreeSpecIds } from '@/lib/requirement-tree';
import type {
    EnumOption,
    RequirementsAbilities,
    RequirementsProject,
    SpecDetail,
    TreeSpec,
} from '@/types/requirements';

export default function SpecDetailPane({
    project,
    tree,
    spec,
    can,
    statuses,
    types,
}: {
    project: RequirementsProject;
    tree: TreeSpec[];
    spec: SpecDetail;
    can: RequirementsAbilities;
    statuses: EnumOption[];
    types: EnumOption[];
}) {
    const ownSubtree = subtreeSpecIds(tree, spec.id);
    const here = String(spec.parent_id ?? '');
    const [moveTo, setMoveTo] = useState(here);

    return (
        <div className="space-y-6">
            <div>
                <p className="text-muted-foreground text-sm">
                    {spec.path.map((step) => step.name).join(' / ')}
                </p>
                <h1 className="text-2xl font-semibold">{spec.name}</h1>
                <p className="text-muted-foreground text-sm">{spec.doc_id}</p>
            </div>

            {!can.manage ? (
                <Card>
                    <CardContent className="space-y-2 pt-6">
                        <RichText
                            html={spec.description}
                            empty="No description."
                        />
                    </CardContent>
                </Card>
            ) : (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle>Specification</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...RequirementSpecController.update.form(
                                    spec.id,
                                )}
                                options={{ preserveScroll: true }}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="spec-name">
                                                Name
                                            </Label>
                                            <Input
                                                id="spec-name"
                                                name="name"
                                                defaultValue={spec.name}
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="spec-doc">
                                                Document id
                                            </Label>
                                            <Input
                                                id="spec-doc"
                                                name="doc_id"
                                                defaultValue={spec.doc_id}
                                                required
                                            />
                                            <InputError
                                                message={errors.doc_id}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>Description</Label>
                                            <RichTextEditor
                                                name="description"
                                                defaultValue={
                                                    spec.description ?? ''
                                                }
                                                aria-label="Specification description"
                                            />
                                        </div>
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
                            <CardTitle>Add</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <Form
                                {...RequirementSpecController.store.form(
                                    project.id,
                                )}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="space-y-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="parent_id"
                                            value={spec.id}
                                        />
                                        <Label htmlFor="child-spec-name">
                                            Child specification
                                        </Label>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <Input
                                                id="child-spec-name"
                                                name="name"
                                                placeholder="Name"
                                                required
                                            />
                                            <Input
                                                name="doc_id"
                                                placeholder="Document id"
                                                required
                                            />
                                        </div>
                                        <InputError message={errors.name} />
                                        <InputError message={errors.doc_id} />
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            disabled={processing}
                                        >
                                            <Plus className="size-4" />
                                            Add specification
                                        </Button>
                                    </>
                                )}
                            </Form>

                            <Form
                                {...RequirementController.store.form(spec.id)}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="space-y-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Label htmlFor="req-name">
                                            Requirement
                                        </Label>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <Input
                                                id="req-name"
                                                name="name"
                                                placeholder="Name"
                                                required
                                            />
                                            <Input
                                                name="doc_id"
                                                placeholder="REQ-LOGIN-1"
                                                required
                                            />
                                        </div>
                                        <div className="grid gap-2 sm:grid-cols-3">
                                            <select
                                                name="status"
                                                defaultValue="draft"
                                                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                            >
                                                {statuses.map((status) => (
                                                    <option
                                                        key={status.value}
                                                        value={status.value}
                                                    >
                                                        {status.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <select
                                                name="type"
                                                defaultValue="feature"
                                                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
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
                                            <Input
                                                name="expected_coverage"
                                                type="number"
                                                min={0}
                                                defaultValue={1}
                                            />
                                        </div>
                                        <InputError message={errors.name} />
                                        <InputError message={errors.doc_id} />
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            disabled={processing}
                                        >
                                            <Plus className="size-4" />
                                            Add requirement
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
                            <Form
                                {...RequirementSpecController.move.form(
                                    spec.id,
                                )}
                                options={{ preserveScroll: true }}
                                className="space-y-2"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Label htmlFor="move-parent">
                                            Move into
                                        </Label>
                                        <div className="flex items-start gap-2">
                                            <SpecPicker
                                                id="move-parent"
                                                name="parent_id"
                                                specs={tree}
                                                exclude={ownSubtree}
                                                rootLabel={`${project.name} (top level)`}
                                                value={moveTo}
                                                onValueChange={setMoveTo}
                                            />
                                            <Button
                                                size="sm"
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
                                {...RequirementSpecController.destroy.form(
                                    spec.id,
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
                                        Delete specification
                                    </Button>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </>
            )}
        </div>
    );
}
