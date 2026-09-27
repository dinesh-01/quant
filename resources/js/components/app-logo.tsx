import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex size-[30px] items-center justify-center rounded-[9px] bg-linear-to-br from-primary to-primary-glow text-[16px] font-extrabold text-white shadow-[0_4px_12px_color-mix(in_srgb,var(--primary)_45%,transparent)]">
                Q
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate text-[17px] leading-tight font-bold tracking-[0.2px] text-white">
                    {name}
                </span>
                <span className="truncate text-[11px] text-[#7c8598]">
                    Test Agents
                </span>
            </div>
        </>
    );
}
