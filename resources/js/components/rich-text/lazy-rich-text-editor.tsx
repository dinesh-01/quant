import { lazy, Suspense, type ComponentProps } from 'react';
import type RichTextEditor from '@/components/rich-text/rich-text-editor';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * The editor, fetched only when a screen actually renders one.
 *
 * TipTap and ProseMirror are around 460 kB of the specification screen's
 * JavaScript, and most of the people on that screen are reading it: a tester
 * without `manage_test_cases` never sees an editor, and neither does anyone
 * looking at a frozen version. Loading it with the page would make every reader
 * pay for the authoring tools.
 *
 * Wrapping the import here rather than at each call site keeps the panes
 * unaware of the split — there are seven of these on a case with two steps.
 */
const Editor = lazy(() => import('@/components/rich-text/rich-text-editor'));

export default function LazyRichTextEditor(
    props: ComponentProps<typeof RichTextEditor>,
) {
    return (
        <Suspense fallback={<Skeleton className="h-32 w-full rounded-md" />}>
            <Editor {...props} />
        </Suspense>
    );
}
