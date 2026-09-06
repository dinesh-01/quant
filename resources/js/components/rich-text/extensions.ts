import { Extension } from '@tiptap/core';
import { Placeholder } from '@tiptap/extensions';
import Image from '@tiptap/extension-image';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import { TableKit } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyleKit } from '@tiptap/extension-text-style';
import StarterKit from '@tiptap/starter-kit';

/**
 * ProseMirror keeps a typed document, not markup: on load it silently drops
 * every element and attribute it has no schema for, and the next save writes
 * back what survived. So the schema below has to cover everything
 * `config/purifier.php` allows, or opening a test case would quietly delete
 * parts of it — and the allow-list is permissive on purpose, because legacy
 * stored raw CKEditor output full of tables, inline colours and images.
 *
 * That makes this file the editor's half of a contract whose other half is the
 * sanitiser. Read them together: anything added there needs a schema here.
 */

/**
 * The node types that may carry a `style` attribute through the editor.
 *
 * Split in two because `TextAlign` owns `text-align` on paragraphs and
 * headings — it parses the declaration into its own attribute and writes it
 * back out. Preserving the raw string there as well would emit the property
 * twice, with the stale copy winning or losing depending on order, so it is
 * stripped for those two types only. Everywhere else, including table cells
 * whose alignment nothing else models, the string is kept verbatim.
 */
const alignedTypes = ['paragraph', 'heading'];

const styledTypes = [
    'blockquote',
    'bulletList',
    'orderedList',
    'listItem',
    'codeBlock',
    'image',
    'table',
    'tableRow',
    'tableCell',
    'tableHeader',
];

const withoutTextAlign = (style: string): string | null => {
    const kept = style
        .split(';')
        .filter((declaration) => declaration.trim() !== '')
        .filter(
            (declaration) =>
                declaration.split(':')[0].trim().toLowerCase() !== 'text-align',
        );

    return kept.length === 0 ? null : `${kept.join(';')};`;
};

/**
 * Carries inline `style` through the editor untouched.
 *
 * Safe to keep verbatim because it is re-sanitised on the way into the
 * database by `App\Casts\SanitizedHtml` — the editor is a convenience, and the
 * cast is the guarantee. Without this, saving an imported test case would strip
 * the formatting the CSS allow-list was written to preserve.
 */
export const PreservedStyle = Extension.create({
    name: 'preservedStyle',

    addGlobalAttributes() {
        return [
            {
                types: styledTypes,
                attributes: {
                    style: {
                        default: null,
                        parseHTML: (element) => element.getAttribute('style'),
                        renderHTML: (attributes) =>
                            attributes.style ? { style: attributes.style } : {},
                    },
                },
            },
            {
                types: alignedTypes,
                attributes: {
                    style: {
                        default: null,
                        parseHTML: (element) => {
                            const style = element.getAttribute('style');

                            return style === null
                                ? null
                                : withoutTextAlign(style);
                        },
                        renderHTML: (attributes) =>
                            attributes.style ? { style: attributes.style } : {},
                    },
                },
            },
        ];
    },
});

/**
 * StarterKit covers paragraphs, headings, lists, blockquotes, rules, code,
 * bold, italic, underline, strike and links; the rest fill in what the
 * allow-list permits and it does not.
 */
export const richTextExtensions = (placeholder?: string) => [
    StarterKit.configure({
        link: {
            openOnClick: false,
            /*
             * The sanitiser refuses anything but http, https and mailto, so
             * matching it here means a rejected link is visibly rejected while
             * typing rather than vanishing on save.
             */
            protocols: ['http', 'https', 'mailto'],
        },
    }),
    TextStyleKit,
    TextAlign.configure({ types: alignedTypes }),
    Subscript,
    Superscript,
    TableKit.configure({ table: { resizable: false } }),
    /*
     * Images are referenced, never embedded: the sanitiser drops `data:` URIs,
     * because a `data:text/html` or SVG payload is script execution wearing an
     * image's clothes. An uploaded attachment is the way to get a picture in.
     */
    Image.configure({ allowBase64: false }),
    Placeholder.configure({ placeholder: placeholder ?? '' }),
    PreservedStyle,
];
