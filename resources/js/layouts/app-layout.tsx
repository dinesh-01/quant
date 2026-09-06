import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { useDisableFormAutocomplete } from '@/hooks/use-disable-form-autocomplete';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    useDisableFormAutocomplete();

    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>
            {children}
        </AppLayoutTemplate>
    );
}
