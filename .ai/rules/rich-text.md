---
paths:
    - 'resources/js/components/rich-text/**'
    - config/purifier.php
    - app/Casts/SanitizedHtml.php
---

# Rich Text

## The editor schema is half a contract with the sanitiser

ProseMirror keeps a typed document, not markup: on load it silently drops every element and attribute it has no schema for, and the next save writes back only what survived. So `extensions.ts` must cover everything `config/purifier.php` allows — add an element or CSS property there and the schema here needs it too, or opening a test case will quietly delete part of it.

`PreservedStyle` carries inline `style` through verbatim, which is safe only because `App\Casts\SanitizedHtml` re-cleans it on write. It skips `text-align` on paragraphs and headings because TextAlign owns that declaration there and both would emit it.

The HTML rides in a hidden input so the editor drops into the uncontrolled `<Form>` pattern the rest of the screens use. An empty document submits `''`, not the `<p></p>` ProseMirror holds, because these columns are nullable.

Import `lazy-rich-text-editor`, never `rich-text-editor` directly: TipTap is ~460 kB and most people on the specification screen are only reading it.

`RichText` uses `dangerouslySetInnerHTML`. Only ever point it at a field that has the `SanitizedHtml` cast; anything else is stored XSS on this origin.

## Ghost include and empty image picker
The editor inserts [ghost] tokens from relatable cases (Include from case…). Kind is summary, preconditions, or step. Omit Version so the include stays live. Image… stays a select of already-uploaded is_image attachments; when none exist show a disabled Upload a file first control. Do not move upload into the editor — nested forms are invalid.
