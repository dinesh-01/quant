<?php

namespace App\Casts;

use App\Actions\Html\HtmlSanitizer;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Sanitises rich text on the way into the database.
 *
 * A cast rather than a call in each action, because the guarantee wanted here
 * is "no row ever holds unsafe markup", and that has to hold for every write
 * path — the actions, the factories, a future XML import, a one-off script.
 * Sanitising in the actions would be five calls today and a forgotten sixth
 * the first time a new entry point is added, and the omission would be
 * invisible until something rendered the field.
 *
 * Only `set` does work. Reading is a hot path and the stored value is already
 * clean, so cleaning again on every read would pay the cost repeatedly for a
 * guarantee already held.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class SanitizedHtml implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        /**
         * Resolved here rather than injected: Eloquent builds casts with `new`
         * and only passes the arguments written into the cast string, so a
         * constructor dependency is never satisfied. HTMLPurifier itself is a
         * singleton behind the facade, so the expensive parsed definitions are
         * still built once and shared.
         */
        return app(HtmlSanitizer::class)->sanitize(is_string($value) ? $value : null);
    }
}
