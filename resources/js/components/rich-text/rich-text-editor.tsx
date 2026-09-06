import { EditorContent, useEditor, type Editor } from '@tiptap/react';
import {
    Bold,
    Code,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Quote,
    Redo2,
    Strikethrough,
    Table as TableIcon,
    Underline as UnderlineIcon,
    Undo2,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { richTextExtensions } from '@/components/rich-text/extensions';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { show as attachmentShow } from '@/routes/attachments';
import type { AttachmentSummary } from '@/types/attachment';
import type { RelatableCase } from '@/types/test-specification';

type GhostKind = 'summary' | 'preconditions' | 'step';

function ghostToken(fullExternalId: string, kind: GhostKind): string {
    if (kind === 'preconditions') {
        return `[ghost]"TestCase":"${fullExternalId}","Preconditions":"1"[/ghost]`;
    }

    if (kind === 'step') {
        return `[ghost]"TestCase":"${fullExternalId}","Step":"1"[/ghost]`;
    }

    return `[ghost]"TestCase":"${fullExternalId}"[/ghost]`;
}

/**
 * A rich text field for the specification's five HTML columns.
 *
 * The HTML rides in a hidden input rather than through client state held by the
 * page, so this drops into the uncontrolled `<Form>` pattern every other field
 * on these screens already uses: the surrounding form serialises the DOM on
 * submit and nothing needs to know an editor is involved.
 *
 * An empty document submits an empty string, not the `<p></p>` ProseMirror
 * keeps internally, because these columns are nullable and a paragraph
 * containing nothing would read as content on every screen that checks.
 */
export default function RichTextEditor({
    name,
    defaultValue,
    placeholder,
    ariaLabel,
    className,
    images = [],
    ghostCases = [],
    ghostKind = 'summary',
}: {
    name: string;
    defaultValue: string | null;
    placeholder?: string;
    ariaLabel?: string;
    className?: string;
    images?: AttachmentSummary[];
    ghostCases?: RelatableCase[];
    ghostKind?: GhostKind;
}) {
    const [html, setHtml] = useState(defaultValue ?? '');

    const editor = useEditor({
        extensions: richTextExtensions(placeholder),
        content: defaultValue ?? '',
        onUpdate: ({ editor }) =>
            setHtml(editor.isEmpty ? '' : editor.getHTML()),
        editorProps: {
            attributes: {
                role: 'textbox',
                'aria-multiline': 'true',
                ...(ariaLabel === undefined ? {} : { 'aria-label': ariaLabel }),
            },
        },
    });

    if (editor === null) {
        return null;
    }

    return (
        <div
            className={cn(
                'rich-text-input border-input focus-within:ring-ring rounded-md border focus-within:ring-1',
                className,
            )}
        >
            <Toolbar
                editor={editor}
                images={images}
                ghostCases={ghostCases}
                ghostKind={ghostKind}
            />

            <div className="rich-text">
                <EditorContent editor={editor} />
            </div>

            <input type="hidden" name={name} value={html} />
        </div>
    );
}

function Toolbar({
    editor,
    images,
    ghostCases,
    ghostKind,
}: {
    editor: Editor;
    images: AttachmentSummary[];
    ghostCases: RelatableCase[];
    ghostKind: GhostKind;
}) {
    /**
     * A link is asked for rather than typed as markup, and an empty answer
     * removes it. `window.prompt` is deliberate: a dialog here would have to
     * live inside the surrounding form, and a form cannot be nested in another.
     */
    const setLink = () => {
        const current = editor.getAttributes('link').href;
        const answer = window.prompt('Link address', current ?? 'https://');

        if (answer === null) {
            return;
        }

        if (answer.trim() === '') {
            editor.chain().focus().unsetLink().run();

            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: answer.trim() })
            .run();
    };

    return (
        <div className="flex flex-wrap items-center gap-0.5 border-b p-1">
            <Control
                label="Bold"
                active={editor.isActive('bold')}
                onClick={() => editor.chain().focus().toggleBold().run()}
            >
                <Bold className="size-3.5" />
            </Control>

            <Control
                label="Italic"
                active={editor.isActive('italic')}
                onClick={() => editor.chain().focus().toggleItalic().run()}
            >
                <Italic className="size-3.5" />
            </Control>

            <Control
                label="Underline"
                active={editor.isActive('underline')}
                onClick={() => editor.chain().focus().toggleUnderline().run()}
            >
                <UnderlineIcon className="size-3.5" />
            </Control>

            <Control
                label="Strikethrough"
                active={editor.isActive('strike')}
                onClick={() => editor.chain().focus().toggleStrike().run()}
            >
                <Strikethrough className="size-3.5" />
            </Control>

            <Control
                label="Code"
                active={editor.isActive('code')}
                onClick={() => editor.chain().focus().toggleCode().run()}
            >
                <Code className="size-3.5" />
            </Control>

            <Separator orientation="vertical" className="mx-1 !h-5" />

            <Control
                label="Heading"
                active={editor.isActive('heading', { level: 3 })}
                onClick={() =>
                    editor.chain().focus().toggleHeading({ level: 3 }).run()
                }
            >
                <span className="text-xs font-semibold">H</span>
            </Control>

            <Control
                label="Bulleted list"
                active={editor.isActive('bulletList')}
                onClick={() => editor.chain().focus().toggleBulletList().run()}
            >
                <List className="size-3.5" />
            </Control>

            <Control
                label="Numbered list"
                active={editor.isActive('orderedList')}
                onClick={() => editor.chain().focus().toggleOrderedList().run()}
            >
                <ListOrdered className="size-3.5" />
            </Control>

            <Control
                label="Quote"
                active={editor.isActive('blockquote')}
                onClick={() => editor.chain().focus().toggleBlockquote().run()}
            >
                <Quote className="size-3.5" />
            </Control>

            <Separator orientation="vertical" className="mx-1 !h-5" />

            <Control
                label="Link"
                active={editor.isActive('link')}
                onClick={setLink}
            >
                <LinkIcon className="size-3.5" />
            </Control>

            <Control
                label="Insert table"
                onClick={() =>
                    editor
                        .chain()
                        .focus()
                        .insertTable({ rows: 2, cols: 2, withHeaderRow: true })
                        .run()
                }
            >
                <TableIcon className="size-3.5" />
            </Control>

            <select
                aria-label="Insert attached image"
                title={
                    images.length === 0
                        ? 'Upload a file first'
                        : 'Insert attached image'
                }
                defaultValue=""
                disabled={images.length === 0}
                className="border-input bg-background h-7 max-w-36 rounded-md border px-1 text-xs disabled:opacity-60"
                onChange={(event) => {
                    const image = images.find(
                        (each) => String(each.id) === event.target.value,
                    );

                    event.target.value = '';

                    if (image === undefined) {
                        return;
                    }

                    editor
                        .chain()
                        .focus()
                        .setImage({
                            src: attachmentShow.url(image.id),
                            alt: image.label,
                        })
                        .run();
                }}
            >
                <option value="">
                    {images.length === 0 ? 'Upload a file first' : 'Image…'}
                </option>
                {images.map((image) => (
                    <option key={image.id} value={image.id}>
                        {image.label}
                    </option>
                ))}
            </select>

            {ghostCases.length > 0 && (
                <select
                    aria-label="Include from case"
                    title="Include from case"
                    defaultValue=""
                    className="border-input bg-background h-7 max-w-44 rounded-md border px-1 text-xs"
                    onChange={(event) => {
                        const selected = ghostCases.find(
                            (each) =>
                                each.full_external_id === event.target.value,
                        );

                        event.target.value = '';

                        if (selected === undefined) {
                            return;
                        }

                        editor
                            .chain()
                            .focus()
                            .insertContent(
                                ghostToken(selected.full_external_id, ghostKind),
                            )
                            .run();
                    }}
                >
                    <option value="">Include from case…</option>
                    {ghostCases.map((other) => (
                        <option
                            key={other.id}
                            value={other.full_external_id}
                        >
                            {other.full_external_id} {other.name}
                        </option>
                    ))}
                </select>
            )}

            <Separator orientation="vertical" className="mx-1 !h-5" />

            <Control
                label="Undo"
                disabled={!editor.can().undo()}
                onClick={() => editor.chain().focus().undo().run()}
            >
                <Undo2 className="size-3.5" />
            </Control>

            <Control
                label="Redo"
                disabled={!editor.can().redo()}
                onClick={() => editor.chain().focus().redo().run()}
            >
                <Redo2 className="size-3.5" />
            </Control>
        </div>
    );
}

/**
 * `type="button"` on every control, because these sit inside the form they are
 * editing a field of and would otherwise submit it.
 */
function Control({
    label,
    active = false,
    disabled = false,
    onClick,
    children,
}: {
    label: string;
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            aria-label={label}
            title={label}
            aria-pressed={active}
            disabled={disabled}
            onClick={onClick}
            className={cn('size-7 p-0', active && 'bg-accent')}
        >
            {children}
        </Button>
    );
}
