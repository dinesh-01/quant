import { Head, Link } from '@inertiajs/react';
import { ClipboardList, FolderTree, ListChecks } from 'lucide-react';
import Heading from '@/components/heading';
import { dashboard } from '@/routes';
import { index as projectIndex } from '@/routes/projects';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    pretitle="Overview"
                    title="Dashboard"
                    description="Open a test project to write cases, plan runs, and record results."
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <Link
                        href={projectIndex()}
                        prefetch
                        className="bg-card text-card-foreground hover:border-primary/40 rounded-lg border p-4 shadow-xs transition-colors"
                    >
                        <ListChecks className="text-primary mb-3 size-5" />
                        <h2 className="font-medium">Test projects</h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Choose the project you want to work in.
                        </p>
                    </Link>

                    <div className="bg-card text-card-foreground rounded-lg border p-4 shadow-xs">
                        <FolderTree className="text-info mb-3 size-5" />
                        <h2 className="font-medium">Specification</h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Suites and cases live on the project once you open
                            one.
                        </p>
                    </div>

                    <div className="bg-card text-card-foreground rounded-lg border p-4 shadow-xs">
                        <ClipboardList className="text-success mb-3 size-5" />
                        <h2 className="font-medium">Plans and execution</h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Builds, assignments, and runs stay scoped to a
                            project.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
