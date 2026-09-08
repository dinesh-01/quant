export default function Heading({
    title,
    description,
    pretitle,
    variant = 'default',
}: {
    title: string;
    description?: string;
    pretitle?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header className={variant === 'small' ? '' : 'mb-8 space-y-0.5'}>
            {pretitle && (
                <p className="text-muted-foreground mb-1 text-xs font-medium tracking-wide uppercase">
                    {pretitle}
                </p>
            )}
            <h2
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-base font-medium'
                        : 'text-xl font-semibold tracking-tight'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="text-muted-foreground text-sm">{description}</p>
            )}
        </header>
    );
}
