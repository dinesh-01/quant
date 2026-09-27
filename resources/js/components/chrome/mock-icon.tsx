import { cn } from '@/lib/utils';

const icons = {
    dashboard:
        '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    folder:
        '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
    cases:
        '<path d="M8 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 12l2 2 4-4"/>',
    plan: '<path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5M2 12l10 5 10-5"/>',
    play: '<circle cx="12" cy="12" r="9"/><path d="M10 8.5 15.5 12 10 15.5z" fill="currentColor" stroke="none"/>',
    chart: '<path d="M3 3v18h18"/><path d="M7 15v3M12 10v8M17 6v12"/>',
    git: '<circle cx="6" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="9" r="2.5"/><path d="M6 8.5v7M18 11.5c0 3-4 3-6 3.5"/>',
    bug: '<path d="M8 6a4 4 0 0 1 8 0"/><rect x="6" y="8" width="12" height="10" rx="6"/><path d="M4 12h2M18 12h2M4 17h3M17 17h3M4 7h2M18 7h2M12 8v10"/>',
    settings:
        '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-2.82 1.17V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 8 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 3.6 15H3.5a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 5 8.6l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 3.6V3.5a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 2.82 1.17l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 20.4 9h.1a2 2 0 0 1 0 4h-.1a1.65 1.65 0 0 0-1 2z"/>',
    users: '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 5.2a3 3 0 0 1 0 5.6M21 20a5 5 0 0 0-4-4.9"/>',
    shield:
        '<path d="M12 3 5 6v5c0 4.5 3 7.7 7 9 4-1.3 7-4.5 7-9V6z"/><path d="m9.5 12 1.8 1.8 3.5-3.6"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
    bell: '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    filter: '<path d="M3 5h18l-7 8v5l-4 2v-7z"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    download:
        '<path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
    'arrow-right': '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'chev-down': '<path d="m6 9 6 6 6-6"/>',
    'trend-up': '<path d="M3 17 9 11l4 4 8-8"/><path d="M17 7h4v4"/>',
    zap: '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
    target:
        '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.4" fill="currentColor"/>',
    sparkles:
        '<path d="M12 3l1.7 4.3L18 9l-4.3 1.7L12 15l-1.7-4.3L6 9l4.3-1.7z"/><path d="M18 14l.9 2.1L21 17l-2.1.9L18 20l-.9-2.1L15 17l2.1-.9z"/>',
    activity: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
    copy: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M4 16V6a2 2 0 0 1 2-2h10"/>',
    edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
    lock: '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
    more: '<circle cx="5" cy="12" r="1.6" fill="currentColor"/><circle cx="12" cy="12" r="1.6" fill="currentColor"/><circle cx="19" cy="12" r="1.6" fill="currentColor"/>',
    flag: '<path d="M5 21V4"/><path d="M5 4h11l-2 4 2 4H5"/>',
    tag: '<path d="M20 13 11 4H4v7l9 9Z"/><circle cx="7.5" cy="7.5" r="1" fill="currentColor"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    paperclip:
        '<path d="m21.4 11.6-9.5 9.5a5 5 0 0 1-7.1-7.1l9.6-9.5a3.5 3.5 0 1 1 4.9 4.9L9.8 19.4a2 2 0 0 1-2.8-2.8l8.5-8.5"/>',
} as const;

export type MockIconName = keyof typeof icons;

export function MockIcon({
    name,
    className,
}: {
    name: MockIconName;
    className?: string;
}) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden
            className={cn('size-4 shrink-0', className)}
            dangerouslySetInnerHTML={{ __html: icons[name] }}
        />
    );
}

function icon(name: MockIconName) {
    return function NavIcon({ className }: { className?: string }) {
        return <MockIcon name={name} className={className} />;
    };
}

export const NavIcons = {
    dashboard: icon('dashboard'),
    folder: icon('folder'),
    plan: icon('plan'),
    play: icon('play'),
    chart: icon('chart'),
    sparkles: icon('sparkles'),
    git: icon('git'),
    bug: icon('bug'),
    users: icon('users'),
    shield: icon('shield'),
    settings: icon('settings'),
    activity: icon('activity'),
};
