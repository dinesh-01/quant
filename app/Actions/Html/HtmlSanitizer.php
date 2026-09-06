<?php

namespace App\Actions\Html;

use Mews\Purifier\Facades\Purifier;

/**
 * Strips anything dangerous out of authored rich text.
 *
 * Legacy stored raw CKEditor output with only a paste-artifact cleanup, which
 * is a stored cross-site scripting hole: whoever could write a test case could
 * run script in the browser of everyone who later read it. That is not
 * reproduced. Everything is sanitised **on write**, so the database never
 * holds markup that would be unsafe to render.
 *
 * On write rather than on read because a stored payload is a live problem even
 * if today's rendering happens to escape it — the next feature to display the
 * field, or an export, or a report, would have to remember. Sanitising once at
 * the boundary makes the guarantee a property of the data.
 *
 * The allow-list lives in `config/purifier.php`, where each decision is
 * explained next to it.
 */
final class HtmlSanitizer
{
    /**
     * The `config/purifier.php` settings key this service uses.
     *
     * Named here rather than in the config so the two cannot drift: the config
     * imports this constant.
     */
    public const PROFILE = 'test_specification';

    /**
     * Nulls and blanks pass through untouched, so an empty field stays empty
     * rather than becoming an empty string or a stray paragraph.
     */
    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        $clean = Purifier::clean($html, self::PROFILE);

        return is_string($clean) ? $clean : null;
    }
}
