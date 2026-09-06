import { useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import SuitePicker from '@/components/test-specification/suite-picker';
import { copyTargets } from '@/routes/specification';
import type {
    SpecificationProject,
    SuiteBranch,
    TreeSuite,
} from '@/types/test-specification';

const selectClasses =
    'border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none';

type CopyTargetProject = {
    id: number;
    name: string;
    tree: SuiteBranch[];
};

/**
 * Chooses a project and suite to copy into.
 *
 * Other projects arrive from `specification.copy-targets` through `useHttp`,
 * so picking one does not replace the specification page. The current project's
 * tree is already on the page and is used until another project is chosen.
 */
export default function CopyTargetPicker({
    project,
    tree,
    name,
    id,
    exclude = [],
    rootLabel,
    defaultSuiteId,
    includeProjectId = false,
}: {
    project: SpecificationProject;
    tree: TreeSuite[];
    name: string;
    id: string;
    exclude?: number[];
    rootLabel?: string;
    defaultSuiteId?: number | null;
    includeProjectId?: boolean;
}) {
    const [projectId, setProjectId] = useState(String(project.id));
    const [targets, setTargets] = useState<CopyTargetProject[]>([]);

    const { get } = useHttp<
        Record<string, never>,
        { projects: CopyTargetProject[] }
    >({});

    useEffect(() => {
        void get(copyTargets.url(project.id), {
            onSuccess: (response) => setTargets(response.projects),
        });
    }, [get, project.id]);

    const isForeign = Number(projectId) !== project.id;
    const selected = isForeign
        ? targets.find((target) => target.id === Number(projectId))
        : { id: project.id, name: project.name, tree };

    return (
        <div className="grid min-w-0 flex-1 gap-2">
            {includeProjectId && (
                <input type="hidden" name="test_project_id" value={projectId} />
            )}

            {targets.length > 0 && (
                <select
                    aria-label="Copy into project"
                    className={selectClasses}
                    value={projectId}
                    onChange={(event) => setProjectId(event.target.value)}
                >
                    <option value={project.id}>
                        {project.name} (this project)
                    </option>

                    {targets.map((target) => (
                        <option key={target.id} value={target.id}>
                            {target.name}
                        </option>
                    ))}
                </select>
            )}

            <SuitePicker
                id={id}
                name={name}
                suites={selected?.tree ?? []}
                exclude={isForeign ? [] : exclude}
                rootLabel={
                    rootLabel === undefined
                        ? undefined
                        : `${selected?.name ?? project.name} (top level)`
                }
                defaultValue={isForeign ? null : defaultSuiteId}
            />
        </div>
    );
}
