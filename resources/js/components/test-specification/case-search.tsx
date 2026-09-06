import { Link, useHttp } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Input } from '@/components/ui/input';
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
}: {
    project: SpecificationProject;
}) {
    const [results, setResults] = useState<SearchResult[]>([]);

    const { data, setData, get, processing } = useHttp<
        { term: string },
        { results: SearchResult[] }
    >({ term: '' });

    useEffect(() => {
        if (data.term === '') {
            setResults([]);

            return;
        }

        const timer = setTimeout(() => {
            void get(search.url(project.id), {
                onSuccess: (response) => setResults(response.results),
                onError: () => setResults([]),
            });
        }, 250);

        return () => clearTimeout(timer);
    }, [data.term, get, project.id]);

    return (
        <div className="space-y-2">
            <div className="relative">
                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2 size-4 -translate-y-1/2" />

                <Input
                    type="search"
                    value={data.term}
                    onChange={(event) => setData('term', event.target.value)}
                    placeholder="Search test cases"
                    aria-label="Search test cases"
                    className="pl-8"
                />
            </div>

            {data.term !== '' && (
                <div className="text-sm">
                    {results.length === 0 ? (
                        <p className="text-muted-foreground px-2 py-1">
                            {processing ? 'Searching…' : 'No matches.'}
                        </p>
                    ) : (
                        <ul className="space-y-0.5">
                            {results.map((result) => (
                                <li key={result.id}>
                                    <Link
                                        href={caseShow([project.id, result.id])}
                                        className="hover:bg-muted block rounded px-2 py-1"
                                    >
                                        <span className="text-muted-foreground mr-2 text-xs">
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
            )}
        </div>
    );
}
