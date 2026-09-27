import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { useDisableFormAutocomplete } from '@/hooks/use-disable-form-autocomplete';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    headerActions,
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    headerActions?: React.ReactNode;
    children: React.ReactNode;
}) {
    useDisableFormAutocomplete();

    return (
        <AppLayoutTemplate
            breadcrumbs={breadcrumbs}
            headerActions={headerActions}
        >
            {children}
        </AppLayoutTemplate>
    );
}
