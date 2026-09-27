import type { Auth } from '@/types/auth';
import type { AvailableProject, CurrentProject } from '@/types/test-project';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            currentProject: CurrentProject | null;
            availableProjects: AvailableProject[];
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
