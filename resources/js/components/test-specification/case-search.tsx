import { Link, useHttp } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';
import { MockIcon } from '@/components/chrome/mock-icon';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { search } from '@/routes/specification';
import { show as caseShow } from '@/routes/specification/cases';
import type {
    SearchResult,
    SpecificationProject,
} from '@/types/test-specification';

/**
 * Searches the project's cases by name or `PREFIX-N` identifier.
 *
 * This hits a JSON endpoint through `useHttp` rather than doing an Inertia
 * visit, so typing neither pushes history entries nor replaces the node shown
 * in the detail pane. Wildcards in the term are escaped server side, so a
 * search for `50%` looks for those literal characters.
 */
export default function CaseSearch({
    project,
    variant = 'panel',
    placeholder = 'Search test cases',
}: {
    project: Pick<SpecificationProject, 'id'>;
    variant?: 'panel' | 'header';
    placeholder?: string;
}) {
    const inputId = useId();
    const [results, setResults] = useState<SearchResult[]>([]);
    const [open, setOpen] = useState(false);

    const { data, setData, get, processing } = useHttp<
        { term: string },
        { results: SearchResult[] }
    >({ term: '' });

    useEffect(() => {
        if (data.term === '') {
            setResults([]);
            setOpen(false);

            return;
        }

        const timer = setTimeout(() => {
            void get(search.url(project.id), {
                onSuccess: (response) => {
                    setResults(response.results);
                    setOpen(true);
                },
                onError: () => setResults([]),
            });
        }, 250);

        return () => clearTimeout(timer);
    }, [data.term, get, project.id]);

    useEffect(() => {
        if (variant !== 'header') {
            return;
        }

        const onKey = (event: KeyboardEvent): void => {
            if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
                event.preventDefault();
                document.getElementById(inputId)?.focus();
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [inputId, variant]);

    const list =
        data.term !== '' && (variant === 'panel' || open) ? (
            <div
                className={cn(
                    'text-sm',
                    variant === 'header' &&
                        'bg-card absolute top-full right-0 left-0 z-30 mt-1 max-h-80 overflow-auto rounded-lg border py-1 shadow-[0_6px_20px_rgba(16,24,40,.08)]',
                )}
            >
                {results.length === 0 ? (
                    <p className="text-muted-foreground px-3 py-2">
                        {processing ? 'Searching…' : 'No matches.'}
                    </p>
                ) : (
                    <ul>
                        {results.map((result) => (
                            <li key={result.id}>
                                <Link
                                    href={caseShow([project.id, result.id])}
                                    className="hover:bg-muted block px-3 py-1.5"
                                    onClick={() => {
                                        setOpen(false);
                                        setData('term', '');
                                    }}
                                >
                                    <span className="text-muted-foreground mr-2 font-mono text-xs">
                                        {result.full_external_id}
                                    </span>
                                    {result.name}
                                    <span className="text-muted-foreground block text-xs">
                                        in {result.suite_name}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        ) : null;

    return (
        <div className={cn('relative', variant === 'panel' && 'space-y-2')}>
            {variant === 'header' ? (
                <div className="border-border bg-muted text-text-subtle relative flex items-center gap-2 rounded-lg border px-3 py-2">
                    <MockIcon name="search" className="size-4 shrink-0" />
                    <Input
                        id={inputId}
                        type="search"
                        value={data.term}
                        onChange={(event) => setData('term', event.target.value)}
                        placeholder={placeholder}
                        aria-label={placeholder}
                        className="h-auto border-0 bg-transparent p-0 shadow-none focus-visible:ring-0"
                    />
                    <kbd className="text-muted-foreground pointer-events-none rounded-[5px] border px-1.5 font-mono text-[11px]">
                        ⌘K
                    </kbd>
                </div>
            ) : (
                <div className="relative">
                    <MockIcon name="search" className="text-muted-foreground pointer-events-none absolute top-1/2 left-2 size-4 -translate-y-1/2" />
                    <Input
                        id={inputId}
                        type="search"
                        value={data.term}
                        onChange={(event) => setData('term', event.target.value)}
                        placeholder={placeholder}
                        aria-label={placeholder}
                        className="pl-8"
                    />
                </div>
            )}
            {list}
        </div>
    );
}
