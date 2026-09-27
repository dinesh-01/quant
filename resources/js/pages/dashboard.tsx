import { Head, Link } from '@inertiajs/react';
import { PageHead } from '@/components/chrome/page-head';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create as createProject, index as projectIndex } from '@/routes/projects';

export default function Dashboard() {
    return (
        <>
            <Head title="Overview" />
            <div className="flex flex-1 flex-col gap-6 p-6">
                <PageHead
                    title="Welcome to Quanta"
                    description="Create or open a test project to write cases, plan runs, and record results."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={projectIndex()}>All projects</Link>
                            </Button>
                            <Button asChild>
                                <Link href={createProject()}>New project</Link>
                            </Button>
                        </>
                    }
                />
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Overview', href: dashboard() }],
};
