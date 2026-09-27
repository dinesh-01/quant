import type { ReactNode } from 'react';

export function PageHead({
    title,
    description,
    actions,
}: {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <div className="mb-[22px] flex flex-wrap items-start gap-4">
            <div className="min-w-0">
                <h1 className="text-[22px] leading-tight font-bold tracking-[-0.01em]">
                    {title}
                </h1>
                {description && (
                    <p className="text-muted-foreground mt-1 text-[13.5px]">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="ml-auto flex flex-wrap items-center gap-2.5">
                    {actions}
                </div>
            )}
        </div>
    );
}
