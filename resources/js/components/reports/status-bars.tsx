import type { StatusCounts } from '@/types/reports';

const bands: { key: keyof StatusCounts; label: string; className: string }[] = [
    { key: 'passed', label: 'Passed', className: 'bg-success' },
    { key: 'failed', label: 'Failed', className: 'bg-destructive' },
    { key: 'blocked', label: 'Blocked', className: 'bg-warning' },
    { key: 'not_run', label: 'Not run', className: 'bg-muted-foreground/40' },
];

export default function StatusBars({
    counts,
    total,
}: {
    counts: StatusCounts;
    total: number;
}) {
    return (
        <div className="space-y-3">
            <div className="bg-muted flex h-3 overflow-hidden rounded-full">
                {bands.map((band) => {
                    const value = counts[band.key];

                    if (value === 0 || total === 0) {
                        return null;
                    }

                    return (
                        <div
                            key={band.key}
                            className={band.className}
                            style={{ width: `${(value / total) * 100}%` }}
                        />
                    );
                })}
            </div>

            <ul className="grid gap-2 sm:grid-cols-4">
                {bands.map((band) => (
                    <li key={band.key} className="text-sm">
                        <span className="text-muted-foreground">
                            {band.label}
                        </span>
                        <span className="ml-2 font-medium">
                            {counts[band.key]}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
