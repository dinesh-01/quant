import { usePage } from '@inertiajs/react';
import { readLastProjectId } from '@/lib/last-project';
import type { AvailableProject, CurrentProject } from '@/types/test-project';

export function useNavProject(): {
    project: CurrentProject | AvailableProject | null;
    currentProject: CurrentProject | null;
    availableProjects: AvailableProject[];
} {
    const { currentProject, availableProjects } = usePage().props;

    if (currentProject) {
        return { project: currentProject, currentProject, availableProjects };
    }

    const lastId =
        typeof document === 'undefined' ? null : readLastProjectId();
    const remembered =
        lastId === null
            ? undefined
            : availableProjects.find((item) => item.id === lastId);

    return {
        project: remembered ?? availableProjects[0] ?? null,
        currentProject,
        availableProjects,
    };
}
