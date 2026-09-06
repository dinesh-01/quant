import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import RequirementSpecController from '@/actions/App/Http/Controllers/Requirements/RequirementSpecController';
import RequirementDetailPane from '@/components/requirements/requirement-detail';
import SpecDetailPane from '@/components/requirements/spec-detail';
import SpecTree from '@/components/requirements/spec-tree';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { show } from '@/routes/requirements';
import type {
    EnumOption,
    RequirementsAbilities,
    RequirementsProject,
    RequirementsSelection,
    TreeSpec,
} from '@/types/requirements';

type RequirementsPageProps = {
    project: RequirementsProject;
    tree: TreeSpec[];
    can: RequirementsAbilities;
    statuses: EnumOption[];
    types: EnumOption[];
    selected: RequirementsSelection;
};

export default function RequirementsIndex({
    project,
    tree,
    can,
    statuses,
    types,
    selected,
}: RequirementsPageProps) {
    setLayoutProps({
        breadcrumbs: [
            {
                title: `${project.name} requirements`,
                href: show(project.id),
            },
        ],
    });

    return (
        <>
            <Head title={`${project.name} requirements`} />

            <div className="flex min-h-0 flex-1 flex-col gap-4 p-4 lg:flex-row">
                <aside className="w-full shrink-0 space-y-4 lg:w-80">
                    <div>
                        <h2 className="text-sm font-medium">{project.name}</h2>
                        <p className="text-muted-foreground text-xs">
                            Requirements
                        </p>
                    </div>

                    <nav aria-label="Requirement specifications">
                        <SpecTree
                            project={project}
                            specs={tree}
                            selected={
                                selected === null
                                    ? null
                                    : {
                                          type: selected.type,
                                          id:
                                              selected.type === 'spec'
                                                  ? selected.spec.id
                                                  : selected.requirement.id,
                                      }
                            }
                        />
                    </nav>

                    {can.manage && (
                        <>
                            <Separator />
                            <Form
                                {...RequirementSpecController.store.form(
                                    project.id,
                                )}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="space-y-2"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Input
                                            name="name"
                                            placeholder="New specification"
                                            aria-label="New specification name"
                                            required
                                        />
                                        <Input
                                            name="doc_id"
                                            placeholder="Document id"
                                            aria-label="New specification document id"
                                            required
                                        />
                                        <InputError message={errors.name} />
                                        <InputError message={errors.doc_id} />
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            <Plus className="size-4" />
                                            Add specification
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </>
                    )}
                </aside>

                <Separator className="lg:hidden" />

                <main className="min-w-0 flex-1">
                    {selected === null ? (
                        <p className="text-muted-foreground text-sm">
                            Select a specification or requirement from the
                            tree.
                        </p>
                    ) : selected.type === 'spec' ? (
                        <SpecDetailPane
                            key={selected.spec.id}
                            project={project}
                            tree={tree}
                            spec={selected.spec}
                            can={can}
                            statuses={statuses}
                            types={types}
                        />
                    ) : (
                        <RequirementDetailPane
                            key={`${selected.requirement.id}:${selected.requirement.version?.version ?? 'none'}`}
                            project={project}
                            tree={tree}
                            requirement={selected.requirement}
                            can={can}
                            statuses={statuses}
                            types={types}
                        />
                    )}
                </main>
            </div>
        </>
    );
}
