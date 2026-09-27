import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Sparkles } from 'lucide-react';
import { PageHead } from '@/components/chrome/page-head';
import { Button } from '@/components/ui/button';
import { index as customReports } from '@/routes/custom-reports';
import { project as projectReports } from '@/routes/reports';

type Props = {
    project: { id: number; name: string };
};

const suggestions = [
    'Show latest run report',
    'Automation coverage by suite',
    'Failed cases this week',
    'Modules with the lowest coverage',
    'Flaky tests this month',
];

export default function CustomReports({ project }: Props) {
    const [prompt, setPrompt] = useState('');
    const [asked, setAsked] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Custom Reports', href: customReports(project.id) },
        ],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setAsked(true);
    };

    return (
        <>
            <Head title={`${project.name} custom reports`} />
            <div className="flex-1 space-y-5 p-6">
                <PageHead
                    title="Custom Reports"
                    description="Ask AI to build a report from your test data in plain English — then save, schedule or share it."
                    actions={
                        <Link
                            href={projectReports(project.id)}
                            className="text-muted-foreground text-sm font-semibold"
                        >
                            Standard
                        </Link>
                    }
                />

                <div
                    className="rounded-2xl border border-primary-100 p-[22px] shadow-[0_1px_2px_rgba(16,24,40,.06)]"
                    style={{
                        background:
                            'radial-gradient(120% 150% at 0% 0%, color-mix(in srgb, var(--primary) 10%, transparent), transparent 55%), radial-gradient(120% 150% at 100% 0%, color-mix(in srgb, var(--primary-glow) 12%, transparent), transparent 55%), var(--card)',
                    }}
                >
                    <div className="flex items-center gap-2.5">
                        <span className="flex size-[34px] items-center justify-center rounded-[10px] bg-linear-to-br from-primary to-primary-glow text-white shadow-[0_5px_16px_color-mix(in_srgb,var(--primary)_45%,transparent)]">
                            <Sparkles className="size-[19px]" />
                        </span>
                        <div>
                            <h2 className="text-[17px] font-bold">Ask AI</h2>
                            <p className="text-muted-foreground text-[12.5px]">
                                Describe the report you want. No language model
                                is connected yet.
                            </p>
                        </div>
                    </div>

                    <form
                        onSubmit={submit}
                        className="border-border-strong bg-card focus-within:border-primary focus-within:ring-primary-50 mt-4 flex items-center gap-2.5 rounded-xl border-[1.5px] py-2 pr-2 pl-3.5 focus-within:ring-[3px]"
                    >
                        <Sparkles className="text-text-subtle size-[18px]" />
                        <input
                            value={prompt}
                            onChange={(event) => setPrompt(event.target.value)}
                            placeholder="e.g. Show me the latest run report for Checkout"
                            className="h-9 flex-1 bg-transparent text-sm outline-none"
                        />
                        <Button
                            type="submit"
                            size="icon"
                            className="size-[38px] rounded-[9px]"
                        >
                            <Sparkles className="size-4" />
                        </Button>
                    </form>

                    <div className="mt-3.5 flex flex-wrap gap-2">
                        {suggestions.map((suggestion) => (
                            <button
                                key={suggestion}
                                type="button"
                                onClick={() => setPrompt(suggestion)}
                                className="border-border bg-card text-muted-foreground hover:border-primary hover:bg-primary-50 hover:text-primary-700 inline-flex items-center rounded-full border px-3 py-1.5 text-[12.5px] font-semibold"
                            >
                                {suggestion}
                            </button>
                        ))}
                    </div>
                </div>

                {asked && (
                    <div className="bg-card rounded-xl border p-5 shadow-[0_1px_2px_rgba(16,24,40,.06)]">
                        <p className="text-muted-foreground text-sm">
                            No AI provider is configured, so nothing was sent.
                            The prompt stays on this page only.
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}
