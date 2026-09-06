import type { Auth } from '@/types/auth';
import type { CurrentProject } from '@/types/test-project';

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
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
