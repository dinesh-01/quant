import { cn } from '@/lib/utils';

/**
 * Renders a stored rich text field.
 *
 * `dangerouslySetInnerHTML` is safe here for one specific reason: every one of
 * these fields is cast through `App\Casts\SanitizedHtml` on the way into the
 * database, so no row can hold markup the allow-list refuses. Do not point this
 * at anything that has not been through that cast — a string straight off a
 * request, or a field that was never given the cast, would be stored cross-site
 * scripting rendered on this origin, which is exactly the hole legacy had.
 */
export default function RichText({
    html,
    empty = 'Nothing written yet.',
    className,
}: {
    html: string | null;
    empty?: string;
    className?: string;
}) {
    if (html === null || html.trim() === '') {
        return (
            <p className={cn('text-muted-foreground text-sm', className)}>
                {empty}
            </p>
        );
    }

    return (
        <div
            className={cn('rich-text text-sm', className)}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}
