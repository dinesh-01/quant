import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function StatCard({
    label,
    value,
    hint,
    icon,
    badgeIcon,
    tone = 'primary',
    hintTone,
    valueClassName,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    icon?: ReactNode;
    badgeIcon?: ReactNode;
    tone?: 'primary' | 'success' | 'info' | 'warning' | 'neutral';
    hintTone?: 'up' | 'down' | 'muted';
    valueClassName?: string;
}) {
    const badge = {
        primary: 'bg-primary-50 text-primary-700',
        success: 'bg-success-bg text-success',
        info: 'bg-info-bg text-info',
        warning: 'bg-warning-bg text-warning',
        neutral: 'bg-neutral-bg text-neutral',
    }[tone];

    const hintClass = {
        up: 'text-success',
        down: 'text-destructive',
        muted: 'text-muted-foreground',
    }[hintTone ?? 'muted'];

    return (
        <div className="bg-card rounded-xl border p-[18px] shadow-[0_1px_2px_rgba(16,24,40,.06)]">
            <div className="flex items-start justify-between gap-3">
                <span className="text-muted-foreground flex items-center gap-2 text-[12.5px] font-semibold">
                    {icon}
                    {label}
                </span>
                {(badgeIcon ?? icon) && (
                    <span
                        className={cn(
                            'flex size-[34px] items-center justify-center rounded-[10px]',
                            badge,
                        )}
                    >
                        {badgeIcon ?? icon}
                    </span>
                )}
            </div>
            <div
                className={cn(
                    'mt-2 mb-0.5 text-[30px] leading-none font-extrabold tracking-[-0.02em]',
                    valueClassName,
                )}
            >
                {value}
            </div>
            {hint && (
                <div className={cn('mt-2 text-[12.5px] font-semibold', hintClass)}>
                    {hint}
                </div>
            )}
        </div>
    );
}

const pillTone: Record<string, string> = {
    passed: 'text-success bg-success-bg border-success-border',
    pass: 'text-success bg-success-bg border-success-border',
    completed: 'text-success bg-success-bg border-success-border',
    active: 'text-success bg-success-bg border-success-border',
    failed: 'text-destructive bg-destructive-bg border-destructive-border',
    fail: 'text-destructive bg-destructive-bg border-destructive-border',
    blocked: 'text-warning bg-warning-bg border-warning-border',
    invited: 'text-warning bg-warning-bg border-warning-border',
    running: 'text-info bg-info-bg border-info-border',
    in_progress: 'text-info bg-info-bg border-info-border',
    now: 'text-info bg-info-bg border-info-border',
    not_run: 'text-neutral bg-neutral-bg border-neutral-border',
    untested: 'text-neutral bg-neutral-bg border-neutral-border',
    skipped: 'text-neutral bg-neutral-bg border-neutral-border',
    draft: 'text-neutral bg-neutral-bg border-neutral-border',
    archived: 'text-neutral bg-neutral-bg border-neutral-border',
    deactivated: 'text-neutral bg-neutral-bg border-neutral-border',
};

const pillDot: Record<string, string> = {
    passed: 'bg-success',
    pass: 'bg-success',
    completed: 'bg-success',
    active: 'bg-success',
    failed: 'bg-destructive',
    fail: 'bg-destructive',
    blocked: 'bg-warning',
    invited: 'bg-warning',
    running: 'bg-info',
    in_progress: 'bg-info',
    now: 'bg-info',
    not_run: 'bg-neutral',
    untested: 'bg-neutral',
    skipped: 'bg-neutral',
    draft: 'bg-neutral',
    archived: 'bg-neutral',
    deactivated: 'bg-neutral',
};

export function StatusPill({
    status,
    label,
    className,
}: {
    status: string;
    label?: string;
    className?: string;
}) {
    const key = status.toLowerCase().replace(' ', '_');

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold capitalize',
                pillTone[key] ?? 'text-neutral bg-neutral-bg border-neutral-border',
                className,
            )}
        >
            <span
                className={cn(
                    'size-[7px] rounded-full',
                    pillDot[key] ?? 'bg-neutral',
                )}
            />
            {label ?? status.replaceAll('_', ' ')}
        </span>
    );
}

export function Tag({
    children,
    tone = 'primary',
}: {
    children: ReactNode;
    tone?: 'primary' | 'neutral' | 'info' | 'danger';
}) {
    const styles = {
        primary: 'bg-primary-50 text-primary-700',
        neutral: 'bg-neutral-bg text-neutral',
        info: 'bg-info-bg text-info',
        danger: 'bg-destructive-bg text-destructive',
    }[tone];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11.5px] font-semibold',
                styles,
            )}
        >
            {children}
        </span>
    );
}

export function PriorityMark({
    priority,
}: {
    priority: string;
}) {
    const key = priority.toLowerCase();
    const tone =
        key === 'high'
            ? 'text-destructive'
            : key === 'medium' || key === 'med'
              ? 'text-warning'
              : 'text-neutral';
    const dot =
        key === 'high'
            ? 'bg-destructive'
            : key === 'medium' || key === 'med'
              ? 'bg-warning'
              : 'bg-neutral';

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 text-[12.5px] font-semibold capitalize',
                tone,
            )}
        >
            <span className={cn('size-2 rounded-full', dot)} />
            {priority}
        </span>
    );
}

const avatarPalette = [
    'bg-[#6d5efc]',
    'bg-[#0e9f6e]',
    'bg-[#e5714b]',
    'bg-[#0e7fb8]',
    'bg-[#b7791f]',
    'bg-[#9333ea]',
];

export function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

const avatarByInitials: Record<string, string> = {
    MS: 'bg-[#6d5efc]',
    SS: 'bg-[#0e9f6e]',
    RT: 'bg-[#e5714b]',
    AR: 'bg-[#0e7fb8]',
    DD: 'bg-[#b7791f]',
};

export function UserAvatar({
    name,
    size = 'md',
}: {
    name: string;
    size?: 'sm' | 'md' | 'lg';
}) {
    const letters = initials(name);
    const hue = name
        .split('')
        .reduce((sum, char) => sum + char.charCodeAt(0), 0);

    return (
        <span
            className={cn(
                'inline-grid place-items-center rounded-full font-bold text-white',
                size === 'sm' && 'size-5 text-[9px]',
                size === 'md' && 'size-7 text-[11.5px]',
                size === 'lg' && 'size-8 text-xs',
                avatarByInitials[letters] ??
                    avatarPalette[hue % avatarPalette.length],
            )}
        >
            {letters}
        </span>
    );
}

export function AvatarStack({ names }: { names: string[] }) {
    if (names.length === 0) {
        return null;
    }

    return (
        <div className="flex">
            {names.map((name, index) => (
                <span
                    key={`${name}-${index}`}
                    className={cn(index > 0 && '-ml-2')}
                    style={{ zIndex: names.length - index }}
                >
                    <span className="ring-card inline-flex rounded-full ring-2">
                        <UserAvatar name={name} />
                    </span>
                </span>
            ))}
        </div>
    );
}

export function ProgressMeter({
    counts,
    total,
}: {
    counts: {
        passed: number;
        failed: number;
        blocked: number;
        not_run: number;
    };
    total: number;
}) {
    const width = (value: number) =>
        total === 0 ? '0%' : `${(value / total) * 100}%`;
    const done = counts.passed + counts.failed + counts.blocked;
    const pct = total === 0 ? 0 : Math.round((done / total) * 100);

    return (
        <div className="flex items-center gap-3">
            <div className="bg-neutral-bg flex h-2 flex-1 overflow-hidden rounded-full">
                <span
                    className="bg-success h-full"
                    style={{ width: width(counts.passed) }}
                />
                <span
                    className="bg-destructive h-full"
                    style={{ width: width(counts.failed) }}
                />
                <span
                    className="bg-warning h-full"
                    style={{ width: width(counts.blocked) }}
                />
                <span
                    className="h-full bg-[#e3e6ec]"
                    style={{ width: width(counts.not_run) }}
                />
            </div>
            <span className="w-10 text-right text-[13px] font-bold">{pct}%</span>
        </div>
    );
}

export function SegControl({
    items,
    className,
}: {
    items: {
        href?: string;
        label: string;
        active: boolean;
        onClick?: () => void;
    }[];
    className?: string;
}) {
    return (
        <div
            className={cn(
                'bg-muted inline-flex rounded-lg border p-[3px]',
                className,
            )}
        >
            {items.map((item) => {
                const className = cn(
                    'flex-1 rounded-md px-3 py-1.5 text-center text-[12.5px] font-semibold',
                    item.active
                        ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(16,24,40,.06)]'
                        : 'text-muted-foreground',
                );

                if (item.onClick) {
                    return (
                        <button
                            key={item.label}
                            type="button"
                            onClick={item.onClick}
                            className={className}
                        >
                            {item.label}
                        </button>
                    );
                }

                return (
                    <Link
                        key={item.href ?? item.label}
                        href={item.href ?? '#'}
                        className={className}
                    >
                        {item.label}
                    </Link>
                );
            })}
        </div>
    );
}

export function ResultDonut({
    counts,
    total,
    passRate,
}: {
    counts: {
        passed: number;
        failed: number;
        blocked: number;
        not_run: number;
    };
    total: number;
    passRate: number;
}) {
    const toHundred = (value: number) =>
        total === 0 ? 0 : (value / total) * 100;

    return (
        <div className="relative size-40">
            <svg viewBox="0 0 42 42" width="160" height="160">
                <circle
                    cx="21"
                    cy="21"
                    r="15.9"
                    fill="none"
                    stroke="var(--neutral-bg)"
                    strokeWidth="6"
                />
                <circle
                    cx="21"
                    cy="21"
                    r="15.9"
                    fill="none"
                    stroke="var(--success)"
                    strokeWidth="6"
                    strokeDasharray={`${toHundred(counts.passed)} ${100 - toHundred(counts.passed)}`}
                    strokeDashoffset={25}
                    strokeLinecap="butt"
                />
                <circle
                    cx="21"
                    cy="21"
                    r="15.9"
                    fill="none"
                    stroke="var(--destructive)"
                    strokeWidth="6"
                    strokeDasharray={`${toHundred(counts.failed)} ${100 - toHundred(counts.failed)}`}
                    strokeDashoffset={25 - toHundred(counts.passed)}
                    strokeLinecap="butt"
                />
                <circle
                    cx="21"
                    cy="21"
                    r="15.9"
                    fill="none"
                    stroke="var(--warning)"
                    strokeWidth="6"
                    strokeDasharray={`${toHundred(counts.blocked)} ${100 - toHundred(counts.blocked)}`}
                    strokeDashoffset={
                        25 -
                        toHundred(counts.passed) -
                        toHundred(counts.failed)
                    }
                    strokeLinecap="butt"
                />
            </svg>
            <div className="absolute inset-0 grid place-items-center text-center">
                <div>
                    <div className="text-[28px] leading-none font-extrabold">
                        {passRate}%
                    </div>
                    <div className="text-muted-foreground text-xs">pass</div>
                </div>
            </div>
        </div>
    );
}
