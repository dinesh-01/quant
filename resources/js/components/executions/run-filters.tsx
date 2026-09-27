import { MockIcon } from '@/components/chrome/mock-icon';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { importanceLabels } from '@/types/test-specification';
import type { RunFilterState } from './use-run-filters';

export function RunFilters({
    filters,
    options,
    onChange,
    onClear,
}: {
    filters: RunFilterState;
    options: {
        suites: string[];
        priorities: string[];
        keywords: string[];
        platforms: string[];
    };
    onChange: (next: RunFilterState) => void;
    onClear: () => void;
}) {
    return (
        <div className="mb-4 flex flex-wrap items-center gap-2.5">
            <FilterButton
                icon="folder"
                label={
                    filters.suite === null
                        ? 'Suite: All'
                        : `Suite: ${filters.suite}`
                }
                active={filters.suite !== null}
                items={[
                    {
                        label: 'All suites',
                        onSelect: () => onChange({ ...filters, suite: null }),
                    },
                    ...options.suites.map((suite) => ({
                        label: suite,
                        onSelect: () => onChange({ ...filters, suite }),
                    })),
                ]}
            />
            <FilterButton
                icon="flag"
                label={
                    filters.priority === null
                        ? 'Priority'
                        : `Priority: ${importanceLabels[filters.priority] ?? filters.priority}`
                }
                active={filters.priority !== null}
                items={[
                    {
                        label: 'Any priority',
                        onSelect: () =>
                            onChange({ ...filters, priority: null }),
                    },
                    ...options.priorities.map((priority) => ({
                        label: importanceLabels[priority] ?? priority,
                        onSelect: () => onChange({ ...filters, priority }),
                    })),
                ]}
            />
            <FilterButton
                icon="users"
                label={
                    filters.assignee === 'me' ? 'Assignee: Me' : 'Assignee: All'
                }
                active={filters.assignee === 'me'}
                items={[
                    {
                        label: 'All assignees',
                        onSelect: () =>
                            onChange({ ...filters, assignee: 'all' }),
                    },
                    {
                        label: 'Assigned to me',
                        onSelect: () =>
                            onChange({ ...filters, assignee: 'me' }),
                    },
                ]}
            />
            <FilterButton
                icon="tag"
                label={
                    filters.keyword === null
                        ? 'Keyword'
                        : `Keyword: ${filters.keyword}`
                }
                active={filters.keyword !== null}
                items={[
                    {
                        label: 'Any keyword',
                        onSelect: () => onChange({ ...filters, keyword: null }),
                    },
                    ...options.keywords.map((keyword) => ({
                        label: keyword,
                        onSelect: () => onChange({ ...filters, keyword }),
                    })),
                ]}
            />
            <FilterButton
                icon="plan"
                label={
                    filters.platform === null
                        ? 'Platform'
                        : `Platform: ${filters.platform}`
                }
                active={filters.platform !== null}
                items={[
                    {
                        label: 'All platforms',
                        onSelect: () =>
                            onChange({ ...filters, platform: null }),
                    },
                    ...options.platforms.map((platform) => ({
                        label: platform,
                        onSelect: () => onChange({ ...filters, platform }),
                    })),
                ]}
            />
            <div className="flex-1" />
            <Button variant="ghost" size="sm" type="button" onClick={onClear}>
                <MockIcon name="x" />
                Clear
            </Button>
        </div>
    );
}

function FilterButton({
    icon,
    label,
    active,
    items,
}: {
    icon: 'folder' | 'flag' | 'users' | 'tag' | 'plan';
    label: string;
    active: boolean;
    items: { label: string; onSelect: () => void }[];
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className={cn(
                    'inline-flex items-center gap-2 rounded-lg border px-3 py-[7px] text-[13px] font-medium',
                    active
                        ? 'border-primary bg-primary-50 text-primary-700'
                        : 'border-border bg-card text-muted-foreground hover:bg-muted',
                )}
            >
                <MockIcon name={icon} className="size-[15px]" />
                {label}
                <MockIcon
                    name="chev-down"
                    className="text-text-subtle size-[14px]"
                />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                {items.map((item) => (
                    <DropdownMenuItem key={item.label} onSelect={item.onSelect}>
                        {item.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
