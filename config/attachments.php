<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disk
    |--------------------------------------------------------------------------
    |
    | The disk attachment contents are written to. It must not be publicly
    | reachable: every download goes through a controller that authorizes the
    | request against the entity the file hangs off, and a disk with a public
    | URL would let anyone bypass that by guessing a path.
    |
    */

    'disk' => env('ATTACHMENTS_DISK', 'attachments'),

    /*
    |--------------------------------------------------------------------------
    | Maximum size
    |--------------------------------------------------------------------------
    |
    | In kilobytes, as Laravel's `max` rule expects. Legacy shipped a 1 MB
    | limit that it never actually enforced in PHP, leaving the real ceiling to
    | `upload_max_filesize`. This one is checked.
    |
    | PHP's own `upload_max_filesize` and `post_max_size` still apply and are
    | usually lower, so raising this alone may not be enough.
    |
    */

    'max_kilobytes' => (int) env('ATTACHMENTS_MAX_KILOBYTES', 10240),

    /*
    |--------------------------------------------------------------------------
    | Allowed extensions
    |--------------------------------------------------------------------------
    |
    | Checked twice: against the name the browser sent, and against the type
    | guessed by reading the file's own bytes. A file has to satisfy both, so
    | neither renaming a payload nor sending a false MIME header gets it in.
    |
    | Legacy's list was doc, xls, gif, png, jpg, xlsx, csv, compared
    | case-sensitively — so PNG was refused while png was accepted. This list
    | adds the formats a modern team actually attaches and is compared without
    | regard to case.
    |
    | Deliberately absent: svg and html, which are documents a browser will
    | execute script from; and every archive format, because what is inside an
    | archive cannot be checked at upload time.
    |
    */

    'extensions' => [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp',
        'pdf', 'txt', 'csv', 'log', 'json', 'xml',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods',
        'mp4', 'webm',
    ],

    /*
    |--------------------------------------------------------------------------
    | Acceptable content
    |--------------------------------------------------------------------------
    |
    | The same list as far as the file's own bytes are concerned, plus `zip`.
    |
    | Every modern Office and OpenDocument format *is* a zip container, and
    | whether the type library recognises one as `.docx` or falls back to
    | `application/zip` depends on how current the host's magic database is. So
    | a strict content list rejects real documents on some machines and not
    | others, which is the worst kind of bug to be told about.
    |
    | The cost of allowing it: a plain zip renamed to `.docx` gets through. That
    | is acceptable here because nothing ever opens an attachment — it is
    | written to a private disk and handed back byte for byte, and the checks
    | that actually matter are the authorization on download and the refusal to
    | show anything but a raster image in the browser.
    |
    */

    'content_extensions' => [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp',
        'pdf', 'txt', 'csv', 'log', 'json', 'xml',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods',
        'mp4', 'webm',
        'zip',
    ],

    /*
    |--------------------------------------------------------------------------
    | Types shown in the browser
    |--------------------------------------------------------------------------
    |
    | Everything else downloads as a file. Only raster images are listed: a
    | browser cannot be made to run script from them, which is not true of SVG
    | or PDF. Legacy served every attachment inline under its stored MIME type,
    | which turned any upload into stored cross-site scripting.
    |
    */

    'inline_types' => [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp',
    ],

];
