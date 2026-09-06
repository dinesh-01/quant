import { Link } from '@inertiajs/react';
import { Tag, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { show } from '@/routes/specification';
import { show as caseShow } from '@/routes/specification/cases';
import { show as suiteShow } from '@/routes/specification/suites';
import type { KeywordFilter, KeywordOption } from '@/types/keyword';
import type {
    Selection,
    SpecificationProject,
} from '@/types/test-specification';

/**
 * Narrows the tree to the cases carrying the chosen keywords.
 *
 * Every control is a link, so the filter lives entirely in the URL: it is
 * shareable, the back button undoes it, and no client state can drift from what
 * the server actually applied. The server echoes the filter back after dropping
 * anything it would not honour, which is what this renders.
 */
export default function KeywordFilterPanel({
    project,
    keywords,
    filter,
    selected,
}: {
    project: SpecificationProject;
    keywords: KeywordOption[];
    filter: KeywordFilter;
    selected: Selection;
}) {
    if (keywords.length === 0) {
        return null;
    }

    /**
     * Filtering keeps the selected node, so changing it does not throw away the
     * pane the reader is working in.
     */
    const href = (query: {
        keywords: number[];
        keyword_match: 'any' | 'all' | null;
    }) => {
        if (selected === null) {
            return show(project.id, { query });
        }

        return selected.type === 'suite'
            ? suiteShow([project.id, selected.suite.id], { query })
            : caseShow([project.id, selected.case.id], { query });
    };

    const match = filter.match === 'all' ? 'all' : null;

    const toggled = (id: number) =>
        filter.ids.includes(id)
            ? filter.ids.filter((each) => each !== id)
            : [...filter.ids, id];

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between gap-2">
                <span className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
                    <Tag className="size-3.5" />
                    Keywords
                </span>

                {filter.ids.length > 0 && (
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="h-auto px-1.5 py-0.5 text-xs"
                    >
                        <Link
                            href={href({ keywords: [], keyword_match: null })}
                            preserveScroll
                        >
                            <X className="size-3" />
                            Clear
                        </Link>
                    </Button>
                )}
            </div>

            <ul className="flex flex-wrap gap-1">
                {keywords.map((keyword) => {
                    const on = filter.ids.includes(keyword.id);

                    return (
                        <li key={keyword.id}>
                            <Link
                                href={href({
                                    keywords: toggled(keyword.id),
                                    keyword_match: match,
                                })}
                                preserveScroll
                                aria-pressed={on}
                                className={cn(
                                    'block rounded-full border px-2 py-0.5 text-xs',
                                    on
                                        ? 'bg-primary text-primary-foreground border-primary'
                                        : 'hover:bg-muted text-muted-foreground',
                                )}
                            >
                                {keyword.name}
                            </Link>
                        </li>
                    );
                })}
            </ul>

            {filter.ids.length > 1 && (
                <div className="text-muted-foreground flex items-center gap-2 text-xs">
                    <span>Show cases with</span>

                    {(['any', 'all'] as const).map((mode) => (
                        <Link
                            key={mode}
                            href={href({
                                keywords: filter.ids,
                                keyword_match: mode === 'all' ? 'all' : null,
                            })}
                            preserveScroll
                            aria-current={
                                filter.match === mode ? 'true' : undefined
                            }
                            className={cn(
                                'rounded border px-1.5 py-0.5',
                                filter.match === mode
                                    ? 'bg-muted text-foreground font-medium'
                                    : 'hover:bg-muted',
                            )}
                        >
                            {mode === 'any' ? 'any of these' : 'all of these'}
                        </Link>
                    ))}
                </div>
            )}
        </div>
    );
}
