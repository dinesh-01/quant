<?php

use App\Actions\Html\HtmlSanitizer;

/**
 * HTMLPurifier settings.
 *
 * This package was chosen over `symfony/html-sanitizer` for one reason: it has
 * a real CSS parser, so `CSS.AllowedProperties` can permit presentational
 * declarations while refusing the ones that let content escape its box. The
 * Symfony sanitizer treats `style` as all-or-nothing, which would have meant
 * either no inline styles at all or arbitrary CSS.
 *
 * @see https://htmlpurifier.org/live/configdoc/plain.html
 */
return [
    'encoding' => 'UTF-8',
    'finalize' => true,

    /**
     * With this false, a non-string reaching the sanitiser is an error rather
     * than being quietly passed through. Nulls are handled before we get here.
     */
    'ignoreNonStrings' => false,

    /**
     * HTMLPurifier serialises its parsed definitions here. The directory must
     * be writable; a read-only deployment will throw on the first sanitise
     * rather than at boot, which is why the path is inside `storage`.
     */
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,

    'settings' => [
        /**
         * The profile every rich text field in the test specification uses.
         *
         * Deliberately permissive on markup, because legacy stored raw
         * CKEditor 4.6 output with only a paste-artifact cleanup — so imported
         * content contains headings, tables, inline colours and images, and a
         * narrow allow-list would silently gut it.
         */
        HtmlSanitizer::PROFILE => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',

            'HTML.AllowedElements' => implode(',', [
                // Blocks and structure.
                'p', 'div', 'br', 'hr', 'blockquote', 'pre',
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',

                // Lists, which test steps lean on heavily.
                'ul', 'ol', 'li',

                // Tables: legacy test cases use them for input matrices.
                'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',

                // Inline formatting.
                'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'code',
                'sub', 'sup', 'span', 'a', 'img',
            ]),

            'HTML.AllowedAttributes' => implode(',', [
                /** Global, and the reason CSS filtering below has to be right. */
                'style',

                'a.href', 'a.title',
                'img.src', 'img.alt', 'img.width', 'img.height',
                'td.colspan', 'td.rowspan', 'th.colspan', 'th.rowspan', 'th.scope',
                'table.summary', 'ol.start', 'li.value',
            ]),

            /**
             * The security-critical list, and an **allow**-list: anything absent
             * is stripped.
             *
             * Absent on purpose: `position`, `top`, `left`, `right`, `bottom`
             * and `z-index`, which together let a test case float an element
             * over the surrounding application and capture clicks meant for
             * it; `display`, `visibility` and `opacity`, which let content hide
             * itself from a reviewer while remaining in the document; and
             * `float`, whose legitimate uses `text-align` already covers.
             *
             * Present because they only affect how text looks inside its own
             * box, which is what "preserve legacy formatting" needs.
             */
            'CSS.AllowedProperties' => implode(',', [
                'color', 'background-color',
                'font', 'font-family', 'font-size', 'font-style', 'font-weight',
                'text-align', 'text-decoration', 'text-indent', 'vertical-align',
                'line-height', 'white-space',
                'margin-left', 'margin-right',
                'padding', 'padding-left', 'padding-right', 'padding-top', 'padding-bottom',
                'width', 'height',
                'border', 'border-collapse', 'border-color', 'border-style', 'border-width',
                'list-style-type',
            ]),

            /**
             * `javascript:` is the obvious one, but `data:` matters just as
             * much: a `data:text/html` or SVG payload is script execution
             * wearing an image's clothes. The cost is that a legacy inline
             * base64 image is dropped rather than preserved — the one place
             * this profile is not permissive, and not a line worth crossing.
             */
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],

            /**
             * A test case is written by one team and read by another, so links
             * out of it are untrusted: `noopener` stops the opened page
             * reaching back through `window.opener`.
             */
            'HTML.TargetBlank' => true,
            'HTML.Nofollow' => true,

            /**
             * Left off (the default) so authored content cannot introduce an
             * `id` that collides with the application's own DOM.
             */
            'Attr.EnableID' => false,

            /**
             * Off, unlike the package default. On, it rewrites plain text into
             * paragraphs, which silently changes content on every save and
             * makes the audit trail show edits nobody made.
             */
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,
        ],
    ],
];
